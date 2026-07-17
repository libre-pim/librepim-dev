<?php

declare(strict_types=1);

namespace Pim\Upgrade\Schema;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Oro\Bundle\SecurityBundle\Acl\Persistence\AclManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds the "view attributes" ACLs for products and product models and preserves current behaviour by
 * copying each role's effective "edit attributes" permission onto the matching "view attributes" ACL.
 *
 * Context: the product / product model edit form "Attributes" tab used to be gated by the
 * *_edit_attributes ACL. It is now gated by the new *_view_attributes ACL, so a role can be granted
 * read-only access to attributes (view granted + edit not). Without this migration, existing roles
 * whose edit permission was set through an EXPLICIT ACE would lose the Attributes tab, because the new
 * ACL starts ungranted. Roles that rely on the root grant need nothing: a new action ACL without an
 * explicit ACE inherits the root grant exactly like *_edit_attributes does, so view already resolves
 * the same way as edit for them.
 *
 * @copyright 2026 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
final class Version_8_0_20260717120000_add_product_view_attributes_acl extends AbstractMigration implements ContainerAwareInterface
{
    /** @var array<string, string> new "view" ACL => source "edit" ACL to copy the grant from */
    private const VIEW_TO_EDIT_ACL = [
        'pim_enrich_product_view_attributes' => 'pim_enrich_product_edit_attributes',
        'pim_enrich_product_model_view_attributes' => 'pim_enrich_product_model_edit_attributes',
    ];

    private ?ContainerInterface $container;

    public function up(Schema $schema): void
    {
        $this->disableMigrationWarning();

        foreach (self::VIEW_TO_EDIT_ACL as $viewAcl => $editAcl) {
            if (!$this->aclIsRegistered($viewAcl)) {
                $this->registerAcl($viewAcl);
            }

            foreach ($this->getRoles() as $role) {
                // Idempotent: never touch a role that already carries an explicit "view" ACE.
                if ($this->roleHasExplicitAce($role, $viewAcl)) {
                    continue;
                }

                // Only roles with an EXPLICIT "edit" ACE need copying. Roles without one inherit the
                // root grant for both edit and view identically, so the new ACL already resolves the
                // same way — nothing to do.
                $editAce = $this->getExplicitAce($role, $editAcl);
                if (null === $editAce) {
                    continue;
                }

                $this->addAceToRole($role, $viewAcl, (int) $editAce['mask'], (bool) $editAce['granting']);
            }
        }

        /** @var AclManager $aclManager */
        $aclManager = $this->container->get('oro_security.acl.manager');
        $aclManager->clearCache();
    }

    private function aclIsRegistered(string $acl): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM acl_classes WHERE class_type = :acl',
            ['acl' => $acl]
        );
    }

    private function registerAcl(string $acl): void
    {
        $this->connection->executeQuery(
            'INSERT INTO acl_classes (class_type) VALUES (:acl)',
            ['acl' => $acl]
        );

        $aclClassId = (int) $this->connection->fetchOne(
            'SELECT id FROM acl_classes WHERE class_type = :acl',
            ['acl' => $acl]
        );

        $this->connection->executeQuery(
            <<<SQL
INSERT INTO acl_object_identities (parent_object_identity_id, class_id, object_identifier, entries_inheriting)
VALUES (null, :acl_class_id, 'action', 1)
SQL,
            ['acl_class_id' => $aclClassId]
        );

        $aclObjectIdentityId = (int) $this->connection->fetchOne(
            'SELECT id FROM acl_object_identities WHERE class_id = :acl_class_id',
            ['acl_class_id' => $aclClassId]
        );

        $this->connection->executeQuery(
            <<<SQL
INSERT INTO acl_object_identity_ancestors (object_identity_id, ancestor_id)
VALUES (:object_identity_id, :object_identity_id)
SQL,
            ['object_identity_id' => $aclObjectIdentityId]
        );
    }

    private function roleHasExplicitAce(string $role, string $acl): bool
    {
        return (bool) $this->connection->fetchOne(
            <<<SQL
SELECT COUNT(*)
FROM acl_entries e
JOIN acl_security_identities s ON s.id = e.security_identity_id
JOIN acl_classes c ON c.id = e.class_id
WHERE s.identifier = :role AND c.class_type = :acl
SQL,
            ['role' => $role, 'acl' => $acl]
        );
    }

    /**
     * @return array{mask: int|string, granting: int|string}|null
     */
    private function getExplicitAce(string $role, string $acl): ?array
    {
        $ace = $this->connection->fetchAssociative(
            <<<SQL
SELECT e.mask, e.granting
FROM acl_entries e
JOIN acl_security_identities s ON s.id = e.security_identity_id
JOIN acl_classes c ON c.id = e.class_id
WHERE s.identifier = :role AND c.class_type = :acl
LIMIT 1
SQL,
            ['role' => $role, 'acl' => $acl]
        );

        return false === $ace ? null : $ace;
    }

    private function addAceToRole(string $role, string $acl, int $mask, bool $granting): void
    {
        $classId = (int) $this->connection->fetchOne(
            'SELECT id FROM acl_classes WHERE class_type = :acl',
            ['acl' => $acl]
        );

        $securityIdentityId = (int) $this->connection->fetchOne(
            'SELECT id FROM acl_security_identities WHERE identifier = :role',
            ['role' => $role]
        );

        // ace_order is numbered 0..n across all roles for a given acl_class, within the "action" tree.
        // Mirror the existing add_product_web_api_acl migration: bump the existing orders, insert at 0.
        $this->connection->executeQuery(
            <<<SQL
UPDATE acl_entries
SET ace_order = ace_order + 1
WHERE class_id = :class_id
AND (
    object_identity_id IS NULL
    OR object_identity_id = (
        SELECT aoi.id
        FROM acl_object_identities aoi
        JOIN acl_classes ac ON aoi.class_id = ac.id
        WHERE aoi.object_identifier = "action" AND ac.class_type = "(root)"
        LIMIT 1
    )
)
ORDER BY ace_order DESC
SQL,
            ['class_id' => $classId]
        );

        $this->connection->executeQuery(
            <<<SQL
INSERT INTO acl_entries (
    class_id, object_identity_id, security_identity_id, field_name,
    ace_order, mask, granting, granting_strategy, audit_success, audit_failure
) VALUES (
    :class_id, null, :security_identity_id, null,
    0, :mask, :granting, 'all', 0, 0
)
SQL,
            [
                'class_id' => $classId,
                'security_identity_id' => $securityIdentityId,
                'mask' => $mask,
                'granting' => $granting ? 1 : 0,
            ]
        );
    }

    /**
     * @return string[]
     */
    private function getRoles(): array
    {
        return array_map(
            static fn (array $row): string => $row['identifier'],
            $this->connection->fetchAllAssociative('SELECT identifier FROM acl_security_identities')
        );
    }

    /**
     * Does a non-altering query so Doctrine does not warn that the migration ran no SQL (all real work
     * is done through immediate $this->connection queries, not deferred $this->addSql()).
     */
    private function disableMigrationWarning(): void
    {
        $this->addSql('SELECT 1');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }

    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }
}
