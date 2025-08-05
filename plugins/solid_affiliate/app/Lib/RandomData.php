<?php

namespace SolidAffiliate\Lib;


class RandomData
{
    /**
     * @param int $length
     * 
     * @return string
     */
    public static function string($length = 10)
    {
        $random = bin2hex(openssl_random_pseudo_bytes(4));
        return substr($random, 0, $length);
    }

    /**
     * @param string $domain
     * @return string
     */
    public static function email($domain = 'gmail.com')
    {
        $email = self::string() . '@' . $domain;
        return $email;
    }

    /**
     * @param string $domain
     * @return string
     */
    public static function url($domain = 'solidwpaffiliate.com')
    {
        $url = 'https://www.' . $domain . '/' . self::string();
        return $url;
    }

    /**
     * @param string $keyword
     * @return string
     */
    public static function image_url($keyword = 'surf')
    {
        $random_int = mt_rand(1, 100);
        $url = "https://loremflickr.com/320/240/{$keyword}?random={$random_int}";
        return $url;
    }


    /**
     * @param string $domain
     * @return string
     */
    public static function ip($domain = 'solidwpaffiliate.com')
    {
        $randIP = mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255) . "." . mt_rand(0, 255);
        return $randIP;
    }

    /**
     * @return float
     */
    public static function float()
    {
        return (float)mt_rand(1, 100) / 2.0;
    }

    /**
     * @return string
     */
    public static function date()
    {
        $str = '-' . mt_rand(0, 30) . ' days';
        return Utils::sql_time($str);
    }

    /**
     * @template T
     * 
     * @param array<array-key, T> $array
     * @return T
     */
    public static function from_array($array)
    {
        return $array[array_rand($array)];
    }

    /**
     * @param string $seperator
     * @return string
     */
    public static function name($seperator = ' ')
    {
        $firstname = array(
            'Johnathon',
            'Anthony',
            'Erasmo',
            'Raleigh',
            'Nancie',
            'Tama',
            'Camellia',
            'Augustine',
            'Christeen',
            'Luz',
            'Diego',
            'Lyndia',
            'Thomas',
            'Georgianna',
            'Leigha',
            'Alejandro',
            'Marquis',
            'Joan',
            'Stephania',
            'Elroy',
            'Zonia',
            'Buffy',
            'Sharie',
            'Blythe',
            'Gaylene',
            'Elida',
            'Randy',
            'Margarete',
            'Margarett',
            'Dion',
            'Tomi',
            'Arden',
            'Clora',
            'Laine',
            'Becki',
            'Margherita',
            'Bong',
            'Jeanice',
            'Qiana',
            'Lawanda',
            'Rebecka',
            'Maribel',
            'Tami',
            'Yuri',
            'Michele',
            'Rubi',
            'Larisa',
            'Lloyd',
            'Tyisha',
            'Samatha',
        );

        $lastname = array(
            'Mischke',
            'Serna',
            'Pingree',
            'Mcnaught',
            'Pepper',
            'Schildgen',
            'Mongold',
            'Wrona',
            'Geddes',
            'Lanz',
            'Fetzer',
            'Schroeder',
            'Block',
            'Mayoral',
            'Fleishman',
            'Roberie',
            'Latson',
            'Lupo',
            'Motsinger',
            'Drews',
            'Coby',
            'Redner',
            'Culton',
            'Howe',
            'Stoval',
            'Michaud',
            'Mote',
            'Menjivar',
            'Wiers',
            'Paris',
            'Grisby',
            'Noren',
            'Damron',
            'Kazmierczak',
            'Haslett',
            'Guillemette',
            'Buresh',
            'Center',
            'Kucera',
            'Catt',
            'Badon',
            'Grumbles',
            'Antes',
            'Byron',
            'Volkman',
            'Klemp',
            'Pekar',
            'Pecora',
            'Schewe',
            'Ramage',
        );

        $random_letter = chr(rand(65, 90));

        $name = $firstname[rand(0, count($firstname) - 1)];
        $name .= $seperator;
        $name .= $random_letter;
        $name .= $seperator;
        $name .= $lastname[rand(0, count($lastname) - 1)];

        return $name;
    }

    /**
     * @return string
     */
    public static function sql_date()
    {
        // $int = rand(1262055681, 1262055681);
        // return date("Y-m-d H:i:s", $int);
        return date('Y-m-d H:i:s', strtotime('-' . mt_rand(0, 365) . ' days'));
    }
}
