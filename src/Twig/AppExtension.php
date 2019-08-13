<?php
/**
 * Created by PhpStorm.
 * User: torsten
 * Date: 05.09.2018
 * Time: 09:07
 */

namespace App\Twig;


use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{

    public function getFilters()
    {
        return array(
            // the logic of this filter is now implemented in a different class
            new TwigFilter('ago', array(AppRuntime::class, 'agoFilter')),
        );
    }

}