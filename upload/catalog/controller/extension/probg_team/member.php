<?php
require_once(DIR_SYSTEM . 'library/probg_team/metadata.php');

class ControllerExtensionProbgTeamMember extends Controller {
    public function index() {
        $this->load->language('extension/probg_team/team');
        $this->load->model('extension/probg_team/team');
        $this->load->model('tool/image');
        $this->document->addStyle('catalog/view/javascript/probg_team/probg_team.css');
        $this->document->addStyle('catalog/view/javascript/jquery/magnific/magnific-popup.css');
        $this->document->addStyle('catalog/view/javascript/probg_team/probg_team_lightbox.css');
        $this->document->addScript('catalog/view/javascript/jquery/magnific/jquery.magnific-popup.min.js');
        $this->document->addScript('catalog/view/javascript/probg_team/probg_team_lightbox.js');

        if (!$this->config->get('module_probg_team_status')) {
            return $this->notFound();
        }

        $requested_category_id = isset($this->request->get['probg_team_category_id']) ? (int)$this->request->get['probg_team_category_id'] : 0;
        $team_member_id = isset($this->request->get['probg_team_member_id']) ? (int)$this->request->get['probg_team_member_id'] : 0;
        $section_info = $this->model_extension_probg_team_team->getSectionDescription();
        $member_info = $this->model_extension_probg_team_team->getMember($team_member_id);

        if (!$section_info || !$member_info) {
            return $this->notFound();
        }

        $team_category_id = (int)$member_info['team_category_id'];

        if ($requested_category_id && $requested_category_id !== $team_category_id) {
            return $this->notFound();
        }

        $category_info = $this->model_extension_probg_team_team->getCategory($team_category_id);

        if (!$category_info) {
            return $this->notFound();
        }

        $canonical = $this->url->link('extension/probg_team/member', 'probg_team_category_id=' . $team_category_id . '&probg_team_member_id=' . $team_member_id);
        $expected_route = $this->model_extension_probg_team_team->getExpectedSeoRoute($team_category_id, $team_member_id);
        $this->enforceCanonical($canonical, $expected_route);

        $title = $member_info['meta_title'] ? $member_info['meta_title'] : $member_info['name'];
        $description = ProbgTeamMetadata::cleanText($member_info['meta_description'] ? $member_info['meta_description'] : ($member_info['short_description'] ? $member_info['short_description'] : $member_info['description']));

        $this->document->setTitle($title);
        $this->document->setDescription($member_info['meta_description']);
        $this->document->setKeywords($member_info['meta_keyword']);
        $this->document->addLink($canonical, 'canonical');

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home'));
        $data['breadcrumbs'][] = array('text' => $section_info['title'], 'href' => $this->url->link('extension/probg_team/team'));
        $data['breadcrumbs'][] = array('text' => $category_info['name'], 'href' => $this->url->link('extension/probg_team/category', 'probg_team_category_id=' . $team_category_id));
        $data['breadcrumbs'][] = array('text' => $member_info['name'], 'href' => $canonical);

        $data['heading_title'] = $member_info['name'];
        $data['short_description'] = html_entity_decode($member_info['short_description'], ENT_QUOTES, 'UTF-8');
        $data['description'] = html_entity_decode($member_info['description'], ENT_QUOTES, 'UTF-8');
        $data['category_name'] = $category_info['name'];
        $data['category_href'] = $this->url->link('extension/probg_team/category', 'probg_team_category_id=' . $team_category_id);

        $member_width = max(1, (int)$this->config->get('module_probg_team_member_width'));
        $member_height = max(1, (int)$this->config->get('module_probg_team_member_height'));
        $gallery_width = max(1, (int)$this->config->get('module_probg_team_gallery_width'));
        $gallery_height = max(1, (int)$this->config->get('module_probg_team_gallery_height'));

        $member_image = $this->getSafeImagePath($member_info['image']);

        if ($member_image) {
            $data['thumb'] = $this->model_tool_image->resize($member_image, $member_width, $member_height);
            $data['popup'] = $this->model_tool_image->resize($member_image, 1200, 1200);
        } else {
            $data['thumb'] = $this->model_tool_image->resize('placeholder.png', $member_width, $member_height);
            $data['popup'] = '';
        }

        $data['images'] = array();
        foreach ($this->model_extension_probg_team_team->getMemberImages($team_member_id) as $image) {
            $gallery_image = $this->getSafeImagePath($image['image']);

            if ($gallery_image) {
                $data['images'][] = array(
                    'thumb' => $this->model_tool_image->resize($gallery_image, $gallery_width, $gallery_height),
                    'popup' => $this->model_tool_image->resize($gallery_image, 1200, 1200)
                );
            }
        }

        $data['show_telephone'] = (bool)$this->config->get('module_probg_team_show_telephone');
        $data['show_city'] = (bool)$this->config->get('module_probg_team_show_city');
        $data['show_working_hours'] = (bool)$this->config->get('module_probg_team_show_working_hours');
        $data['show_website'] = (bool)$this->config->get('module_probg_team_show_website');
        $data['show_social'] = (bool)$this->config->get('module_probg_team_show_social');
        $data['telephone'] = $member_info['telephone'];
        $data['telephone_href'] = preg_replace('/[^0-9+]/', '', $member_info['telephone']);
        $data['city'] = $member_info['city'];
        $data['working_hours'] = nl2br(htmlspecialchars((string)$member_info['working_hours'], ENT_QUOTES, 'UTF-8'));
        $data['website'] = $this->sanitizeUrl($member_info['website']);
        $data['facebook'] = $this->sanitizeUrl($member_info['facebook']);
        $data['instagram'] = $this->sanitizeUrl($member_info['instagram']);
        $data['youtube'] = $this->sanitizeUrl($member_info['youtube']);
        $data['linkedin'] = $this->sanitizeUrl($member_info['linkedin']);

        $data['text_category'] = $this->language->get('text_category');
        $data['text_telephone'] = $this->language->get('text_telephone');
        $data['text_city'] = $this->language->get('text_city');
        $data['text_working_hours'] = $this->language->get('text_working_hours');
        $data['text_website'] = $this->language->get('text_website');
        $data['text_social'] = $this->language->get('text_social');
        $data['text_gallery'] = $this->language->get('text_gallery');

        $this->setOpenGraph($title, $description, $canonical, $data['popup'] ? $data['popup'] : $data['thumb'], 'profile');
        $data['structured_data'] = $this->buildStructuredData($canonical, $title, $description, $data['breadcrumbs'], $member_info, $category_info, $data);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/probg_team/member', $data));
    }

    private function buildStructuredData($canonical, $title, $description, $breadcrumbs, $member_info, $category_info, $data) {
        if (!$this->config->get('module_probg_team_schema_status')) {
            return '';
        }

        $base = htmlspecialchars_decode($canonical, ENT_QUOTES);
        $person = array(
            '@type' => 'Person',
            '@id' => $base . '#person',
            'name' => $member_info['name'],
            'url' => $base,
            'jobTitle' => $category_info['name']
        );

        if ($description) {
            $person['description'] = $description;
        }
        if (!empty($data['popup'])) {
            $person['image'] = $data['popup'];
        } elseif (!empty($data['thumb'])) {
            $person['image'] = $data['thumb'];
        }
        if (!empty($data['telephone'])) {
            $person['telephone'] = $data['telephone'];
        }
        if (!empty($data['city'])) {
            $person['homeLocation'] = array('@type' => 'Place', 'name' => $data['city']);
        }
        $same_as = array_values(array_filter(array($data['website'], $data['facebook'], $data['instagram'], $data['youtube'], $data['linkedin'])));
        if ($same_as) {
            $person['sameAs'] = $same_as;
        }

        $graph = array(
            array(
                '@type' => 'ProfilePage',
                '@id' => $base . '#webpage',
                'url' => $base,
                'name' => $title,
                'description' => $description,
                'mainEntity' => array('@id' => $base . '#person')
            ),
            ProbgTeamMetadata::breadcrumbList($breadcrumbs, $base . '#breadcrumbs'),
            $person
        );

        return '<script type="application/ld+json">' . ProbgTeamMetadata::encode(array('@context' => 'https://schema.org', '@graph' => $graph)) . '</script>';
    }

    private function sanitizeUrl($url) {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }

    private function setOpenGraph($title, $description, $url, $image, $type) {
        if (!$this->config->get('module_probg_team_open_graph_status')) {
            return;
        }

        $this->config->set('probg_team_og_title', $title);
        $this->config->set('probg_team_og_description', $description);
        $this->config->set('probg_team_og_url', htmlspecialchars_decode($url, ENT_QUOTES));
        $this->config->set('probg_team_og_image', $image);
        $this->config->set('probg_team_og_type', $type);
    }

    private function enforceCanonical($canonical, $expected_route) {
        if (!$this->config->get('config_seo_url') || !$expected_route) {
            return;
        }

        $current_route = isset($this->request->get['_route_']) ? trim($this->request->get['_route_'], '/') : '';

        if ($current_route !== $expected_route) {
            $this->response->redirect(htmlspecialchars_decode($canonical, ENT_QUOTES), 301);
        }
    }

    private function notFound() {
        $this->request->get['route'] = 'error/not_found';
        return $this->load->controller('error/not_found');
    }
    private function getSafeImagePath($image) {
        $image = ltrim(str_replace('\\', '/', trim((string)$image)), '/');

        if ($image === '' || strpos($image, "\0") !== false || preg_match('#(^|/)\.\.(/|$)#', $image)) {
            return '';
        }

        $root = realpath(DIR_IMAGE);
        $path = realpath(DIR_IMAGE . $image);

        if ($root === false || $path === false || !is_file($path)) {
            return '';
        }

        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        $path = str_replace('\\', '/', $path);

        return strpos($path, $root) === 0 ? $image : '';
    }

}
