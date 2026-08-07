<?php
require_once(DIR_SYSTEM . 'library/probg_team/metadata.php');

class ControllerExtensionProbgTeamCategory extends Controller {
    public function index() {
        $this->load->language('extension/probg_team/team');
        $this->load->model('extension/probg_team/team');
        $this->load->model('tool/image');
        $this->document->addStyle('catalog/view/javascript/probg_team/probg_team.css');

        if (!$this->config->get('module_probg_team_status')) {
            return $this->notFound();
        }

        $team_category_id = isset($this->request->get['probg_team_category_id']) ? (int)$this->request->get['probg_team_category_id'] : 0;
        $page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
        $limit = (int)$this->config->get('module_probg_team_limit');

        if ($limit < 1) {
            $limit = 12;
        }

        $section_info = $this->model_extension_probg_team_team->getSectionDescription();
        $category_info = $this->model_extension_probg_team_team->getCategory($team_category_id);

        if (!$section_info || !$category_info) {
            return $this->notFound();
        }

        $url = 'probg_team_category_id=' . $team_category_id;
        if ($page > 1) {
            $url .= '&page=' . $page;
        }

        $canonical = $this->url->link('extension/probg_team/category', $url);
        $expected_route = $this->model_extension_probg_team_team->getExpectedSeoRoute($team_category_id);
        $this->enforceCanonical($canonical, $expected_route);

        $title = $category_info['meta_title'] ? $category_info['meta_title'] : $category_info['name'];
        $description = ProbgTeamMetadata::cleanText($category_info['meta_description'] ? $category_info['meta_description'] : $category_info['description']);

        $this->document->setTitle($title);
        $this->document->setDescription($category_info['meta_description']);
        $this->document->setKeywords($category_info['meta_keyword']);
        $this->document->addLink($canonical, 'canonical');

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/home'));
        $data['breadcrumbs'][] = array('text' => $section_info['title'], 'href' => $this->url->link('extension/probg_team/team'));
        $data['breadcrumbs'][] = array('text' => $category_info['name'], 'href' => $this->url->link('extension/probg_team/category', 'probg_team_category_id=' . $team_category_id));

        $data['heading_title'] = $category_info['name'];
        $data['description'] = html_entity_decode($category_info['description'], ENT_QUOTES, 'UTF-8');
        $data['text_no_members'] = $this->language->get('text_no_members');
        $data['text_read_more'] = $this->language->get('text_read_more');
        $data['text_city'] = $this->language->get('text_city');
        $data['show_city'] = (bool)$this->config->get('module_probg_team_show_city');

        $list_width = max(1, (int)$this->config->get('module_probg_team_list_width'));
        $list_height = max(1, (int)$this->config->get('module_probg_team_list_height'));
        $members_total = $this->model_extension_probg_team_team->getTotalMembers($team_category_id);
        $members = $this->model_extension_probg_team_team->getMembers(array(
            'team_category_id' => $team_category_id,
            'start' => ($page - 1) * $limit,
            'limit' => $limit
        ));

        $data['members'] = array();

        foreach ($members as $member) {
            if ($member['image'] && is_file(DIR_IMAGE . $member['image'])) {
                $thumb = $this->model_tool_image->resize($member['image'], $list_width, $list_height);
            } else {
                $thumb = $this->model_tool_image->resize('placeholder.png', $list_width, $list_height);
            }

            $data['members'][] = array(
                'team_member_id' => (int)$member['team_member_id'],
                'name' => $member['name'],
                'short_description' => html_entity_decode($member['short_description'], ENT_QUOTES, 'UTF-8'),
                'city' => $member['city'],
                'thumb' => $thumb,
                'href' => $this->url->link('extension/probg_team/member', 'probg_team_category_id=' . $team_category_id . '&probg_team_member_id=' . (int)$member['team_member_id'])
            );
        }

        $pagination = new Pagination();
        $pagination->total = $members_total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('extension/probg_team/category', 'probg_team_category_id=' . $team_category_id . '&page={page}');

        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf(
            $this->language->get('text_pagination'),
            $members_total ? (($page - 1) * $limit) + 1 : 0,
            min($page * $limit, $members_total),
            $members_total,
            $members_total ? ceil($members_total / $limit) : 0
        );

        $og_image = !empty($data['members'][0]['thumb']) ? $data['members'][0]['thumb'] : $this->getStoreLogo();
        $this->setOpenGraph($title, $description, $canonical, $og_image, 'website');
        $data['structured_data'] = $this->buildStructuredData($canonical, $title, $description, $data['breadcrumbs'], $data['members'], $members_total);

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('extension/probg_team/category', $data));
    }

    private function buildStructuredData($canonical, $title, $description, $breadcrumbs, $members, $members_total) {
        if (!$this->config->get('module_probg_team_schema_status')) {
            return '';
        }

        $items = array();
        $position = 1;

        foreach ($members as $member) {
            $person = array(
                '@type' => 'Person',
                'name' => $member['name'],
                'url' => htmlspecialchars_decode($member['href'], ENT_QUOTES)
            );

            if (!empty($member['thumb'])) {
                $person['image'] = $member['thumb'];
            }

            $items[] = array('@type' => 'ListItem', 'position' => $position++, 'item' => $person);
        }

        $base = htmlspecialchars_decode($canonical, ENT_QUOTES);
        $graph = array(
            array(
                '@type' => 'CollectionPage',
                '@id' => $base . '#webpage',
                'url' => $base,
                'name' => $title,
                'description' => $description,
                'mainEntity' => array('@id' => $base . '#itemlist')
            ),
            ProbgTeamMetadata::breadcrumbList($breadcrumbs, $base . '#breadcrumbs'),
            array(
                '@type' => 'ItemList',
                '@id' => $base . '#itemlist',
                'numberOfItems' => (int)$members_total,
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
