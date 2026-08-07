<?php
class ControllerExtensionModuleProbgTeamMenu extends Controller {
    public function index($setting = array()) {
        if (!$this->config->get('module_probg_team_status') || empty($setting['status'])) {
            return '';
        }

        $this->load->language('extension/module/probg_team_menu');
        $this->load->model('extension/probg_team/team');
        $this->document->addStyle('catalog/view/javascript/probg_team/probg_team.css');

        $section_info = $this->model_extension_probg_team_team->getSectionDescription();

        if (!$section_info) {
            return '';
        }

        $language_id = (int)$this->config->get('config_language_id');
        $category_id = !empty($setting['team_category_id']) ? (int)$setting['team_category_id'] : 0;
        $limit = isset($setting['limit']) ? (int)$setting['limit'] : 10;
        $limit = min(1000, max(1, $limit));
        $custom_title = isset($setting['title'][$language_id]) ? trim($setting['title'][$language_id]) : '';
        $default_title = $section_info['title'];
        $data['view_all_href'] = $this->url->link('extension/probg_team/team');

        if ($category_id) {
            $category_info = $this->model_extension_probg_team_team->getCategory($category_id);

            if (!$category_info) {
                return '';
            }

            $default_title = $category_info['name'];
            $data['view_all_href'] = $this->url->link('extension/probg_team/category', 'probg_team_category_id=' . $category_id);
        }

        $data['heading_title'] = $custom_title !== '' ? $custom_title : $default_title;
        $data['text_view_all'] = $this->language->get('text_view_all');
        $data['show_category'] = !$category_id;
        $data['members'] = array();
        $active_member_id = isset($this->request->get['probg_team_member_id']) ? (int)$this->request->get['probg_team_member_id'] : 0;

        $members = $this->model_extension_probg_team_team->getMembers(array(
            'team_category_id' => $category_id,
            'sort' => 'sort_order',
            'start' => 0,
            'limit' => $limit
        ));

        foreach ($members as $member) {
            $data['members'][] = array(
                'name' => $member['name'],
                'category_name' => $member['category_name'],
                'active' => $active_member_id === (int)$member['team_member_id'],
                'href' => $this->url->link('extension/probg_team/member', 'probg_team_category_id=' . (int)$member['team_category_id'] . '&probg_team_member_id=' . (int)$member['team_member_id'])
            );
        }

        if (!$data['members']) {
            return '';
        }

        return $this->load->view('extension/module/probg_team_menu', $data);
    }
}
