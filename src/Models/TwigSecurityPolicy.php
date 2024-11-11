<?php

namespace Crm\SalesFunnelModule\Models;

use Twig\Sandbox\SecurityPolicyInterface;

/**
 * SandboxExtension requires us to provide _some_ policy, this empty one works just fine. Having Twig in sandbox
 * is providing us much better security, even with this kind of setup.
 */
class TwigSecurityPolicy implements SecurityPolicyInterface
{
    public function checkSecurity($tags, $filters, $functions): void
    {
    }

    public function checkMethodAllowed($obj, $method): void
    {
    }

    public function checkPropertyAllowed($obj, $property): void
    {
    }
}
