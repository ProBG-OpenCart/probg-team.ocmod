<?php
class ProbgTeamSlug {
    public static function generate($value) {
        $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8');

        $map = array(
            'А'=>'A','Б'=>'B','В'=>'V','Г'=>'G','Д'=>'D','Е'=>'E','Ж'=>'Zh','З'=>'Z','И'=>'I','Й'=>'Y','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'Ts','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Sht','Ъ'=>'A','Ь'=>'Y','Ю'=>'Yu','Я'=>'Ya',
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sht','ъ'=>'a','ь'=>'y','ю'=>'yu','я'=>'ya',
            'Ё'=>'Yo','ё'=>'yo','Э'=>'E','э'=>'e','Ы'=>'Y','ы'=>'y','Є'=>'Ye','є'=>'ye','І'=>'I','і'=>'i','Ї'=>'Yi','ї'=>'yi','Ґ'=>'G','ґ'=>'g'
        );

        $value = strtr($value, $map);

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        if (function_exists('utf8_strtolower')) {
            $value = utf8_strtolower($value);
        } elseif (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }

        $value = preg_replace('/[^a-z0-9]+/i', '-', $value);
        $value = preg_replace('/-+/', '-', $value);

        return trim($value, '-');
    }
}
