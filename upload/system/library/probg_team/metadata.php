<?php
class ProbgTeamMetadata {
    public static function cleanText($text, $limit = 220) {
        $text = html_entity_decode((string)$text, ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));

        if ($limit > 0 && utf8_strlen($text) > $limit) {
            $text = rtrim(utf8_substr($text, 0, $limit - 1)) . '…';
        }

        return $text;
    }

    public static function encode($data) {
        return json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    public static function breadcrumbList($breadcrumbs, $id) {
        $items = array();
        $position = 1;

        foreach ($breadcrumbs as $breadcrumb) {
            if (empty($breadcrumb['text']) || empty($breadcrumb['href'])) {
                continue;
            }

            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $breadcrumb['text'],
                'item' => htmlspecialchars_decode($breadcrumb['href'], ENT_QUOTES)
            );
        }

        return array(
            '@type' => 'BreadcrumbList',
            '@id' => $id,
            'itemListElement' => $items
        );
    }
}
