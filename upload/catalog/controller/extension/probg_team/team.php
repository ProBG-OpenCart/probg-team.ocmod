<?php
require_once(DIR_SYSTEM . 'library/probg_team/metadata.php');

class ControllerExtensionProbgTeamTeam extends Controller {
    public function index() {
        $this->load->language('extension/probg_team/team');
        $this->load->model('extension/probg_team/team');
        $this->document->addStyle('catalog/view/javascript/probg_team/probg_team.css');

        if (!$this->config->get('module_probg_team_status')) {
            return $this->notFound();
        }

        $section_info = $this->model_extension_probg_team_team->getSectionDescription();

        if (!$section_info) {
            return $this->notFound();
        }

        $canonical = $this->url->link('extension/probg_team/team');
        $expected_route = $this->model_extension_probg_team_team->getExpectedSeoRoute();
        $this->enforceCanonical($canonical, $expected_route);

        $title = $section_info['meta_title'] ? $section_info['meta_title'] : $section_info['title'];
        $description = ProbgTeamMetadata::cleanText($section_info['meta_description'] ? $section_info['meta_description'] : $section_info['description']);

        $this->document->setTitle($title);
        $this->document->setDescription($section_info['meta_description']);
        $this->document->setKeywords($section_info['meta_keyword']);
        $this->document->addLink($canonical, 'canonical');

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home'));
        $data['breadcrumbs'][] = array('text' => $section_info['title'], 'href' => $canonical);

        $data['heading_title'] = $section_info['title'];
        $data['description'] = html_entity_decode($section_info['description'], ENT_QUOTES, 'UTF-8');
        $data['text_members'] = $this->language->get('text_members');
        $data['text_no_categories'] = $this->language->get('text_no_categories');
        $data['categories'] = array();

        $categories = $this->model_extension_probg_team_team->getCategories(array(
            'show_empty' => (bool)$this->config->get('module_probg_team_show_empty_categories')
        ));

        foreach ($categories as $category) {
            $data['categories'][] = array(
                'team_category_id' => (int)$category['team_category_id'],
                'name' => $category['name'],
                'description' => html_entity_decode($category['description'], ENT_QUOTES, 'UTF-8'),
                'member_total' => (int)$category['member_total'],
                'href' => $this->url->link('extension/probg_team/category', 'probg_team_category_id=' . (int)$category['team_category_id'])
            );
        }

        $og_image = $this->getStoreLogo();
        $this->setOpenGraph($title, $description, $canonical, $og_image, 'website');
        $data['structured_data'] = $this->buildStructuredData($canonical, $title, $description, $data['breadcrumbs'], $data['categories']);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/probg_team/team', $data));
    }

    private function buildStructuredData($canonical, $title, $description, $breadcrumbs, $categories) {
        if (!$this->config->get('module_probg_team_schema_status')) {
            return '';
        }

        $items = array();
        $position = 1;

        foreach ($categories as $category) {
            $items[] = array(
                '@type' => 'ListItem',
                'position' => $position++,
                'url' => htmlspecialchars_decode($category['href'], ENT_QUOTES),
                'name' => $category['name']
            );
        }

        $graph = array(
            array(
                '@type' => 'CollectionPage',
                '@id' => htmlspecialchars_decode($canonical, ENT_QUOTES) . '#webpage',
                'url' => htmlspecialchars_decode($canonical, ENT_QUOTES),
                'name' => $title,
                'description' => $description,
                'mainEntity' => array('@id' => htmlspecialchars_decode($canonical, ENT_QUOTES) . '#itemlist')
            ),
            ProbgTeamMetadata::breadcrumbList($breadcrumbs, htmlspecialchars_decode($canonical, ENT_QUOTES) . '#breadcrumbs'),
            array(
                '@type' => 'ItemList',
                '@id' => htmlspecialchars_decode($canonical, ENT_QUOTES) . '#itemlist',
                'numberOfItems' => count($items),
                'itemListElement' => $items
            )
        );

        return '<script type="application/ld+json">' . ProbgTeamMetadata::encode(array('@context' => 'https://schema.org', '@graph' => $graph)) . '</script>';
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

    private function getStoreLogo() {
        $logo = $this->config->get('config_logo');

        if (!$logo || !is_file(DIR_IMAGE . $logo)) {
            return '';
        }

        $server = !empty($this->request->server['HTTPS']) ? $this->config->get('config_ssl') : $this->config->get('config_url');
        return rtrim($server, '/') . '/image/' . ltrim($logo, '/');
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
}
