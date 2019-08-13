<?php
/**
 * Created by PhpStorm.
 * User: torsten
 * Date: 05.09.2018
 * Time: 09:08
 */

namespace App\Twig;


use Twig\Extension\RuntimeExtensionInterface;

class AppRuntime implements RuntimeExtensionInterface
{


    /**
     * AppRuntime constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param \DateTime $ago
     * @return string
     */
    public function agoFilter(\DateTime $ago)
    {
        $now = new \DateTime;
        $diff = $now->diff($ago);

        $diff->w = floor($diff->d / 7);
        $diff->d -= $diff->w * 7;

        $string = array(
            'y' => 'Jahr',
            'm' => 'Monat',
            'w' => 'Woche',
            'd' => 'Tag',
            'h' => 'Stunde',
            'i' => 'Minute',
            's' => 'Sekunde',
        );
        foreach ($string as $k => &$v) {
            if ($diff->$k) {
                $v = $diff->$k . ' ' . $v . (($diff->$k > 1) && ($v == 'Stunde' || $v == 'Woche' || $v == 'Minute' || $v == 'Sekunde') ? 'n' : (($diff->$k > 1) && ($v == 'Monat' || $v == 'Jahr' || $v == 'Tag') ? 'en' : ''));
            } else {
                unset($string[$k]);
            }
        }

        $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . '' : '';
    }

}