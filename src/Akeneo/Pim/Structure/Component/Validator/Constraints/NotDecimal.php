<?php
declare(strict_types=1);

namespace Akeneo\Pim\Structure\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @author    Willy Mesnage <willy.mesnage@akeneo.com>
 * @copyright 2020 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class NotDecimal extends Constraint
{
    /** @var string */
    public $message = 'This value should not be a decimal.';

    // Add this property to define the default option
    public $allowDecimal = false;
    
    // Add this method to specify the default option
    public function getDefaultOption()
    {
        return 'allowDecimal';
    }
    
    public function getRequiredOptions()
    {
        return [];
    }
}
