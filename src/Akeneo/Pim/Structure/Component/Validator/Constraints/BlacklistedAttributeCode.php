<?php

namespace Akeneo\Pim\Structure\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

class BlacklistedAttributeCode extends Constraint
{
    /**
     * Violation message for blacklisted attribute identifiers
     */
    public string $message = 'pim_catalog.constraint.blacklisted_attribute_code';
    public string $internalAPIMessage = 'pim_catalog.constraint.blacklisted_attribute_code_with_link';

    public function validatedBy(): string
    {
        return 'pim_blacklisted_attribute_code_validator';
    }
    
    // Add this property to define the default option
    public $blacklistedCodes = [];
    
    // Add this method to specify the default option
    public function getDefaultOption()
    {
        return 'blacklistedCodes';
    }
    
    public function getRequiredOptions()
    {
        return ['blacklistedCodes'];
    }
}
