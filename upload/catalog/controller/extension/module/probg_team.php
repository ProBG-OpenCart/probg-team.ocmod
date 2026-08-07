<?php
class ControllerExtensionModuleProbgTeam extends Controller {
    public function index($setting = array()) {
        if (!$this->config->get('module_probg_team_status') || (isset($setting['status']) && !$setting['status'])) {
            return '';
        }

        $this->load->language('extension/module/probg_team');
        $this->load->model('extension/probg_team/team');
        $this->load->model('tool/image');
        $this->document->addStyle('catalog/view/javascript/probg_team/probg_team.css');

        $section_info = $this->model_extension_probg_team_team->getSectionDescription();

        if (!$section_info) {
            return '';
        }

        $language_id = (int)$this->config->get('config_language_id');
        $limit = !empty($setting['limit']) ? (int)$setting['limit'] : (int)$this->config->get('module_probg_team_limit');
        $limit = max(1, $limit ? $limit : 12);
        $columns = !empty($setting['columns']) ? (int)$setting['columns'] : 4;
        $allowed_columns = array(1, 2, 3, 4, 6);

        if (!in_array($columns, $allowed_columns, true)) {
            $columns = 4;
        }

        $width = max(1, (int)$this->config->get('module_probg_team_list_width'));
        $height = max(1, (int)$this->config->get('module_probg_team_list_height'));
        $category_id = !empty($setting['team_category_id']) ? (int)$setting['team_category_id'] : 0;
        $sort = isset($setting['sort']) ? $setting['sort'] : 'sort_order';
        $allowed_sort = array('sort_order', 'name', 'date_added');

        if (!in_array($sort, $allowed_sort, true)) {
            $sort = 'sort_order';
        }

        $custom_title = isset($setting['title'][$language_id]) ? trim($setting['title'][$language_id]) : '';
        $default_title = $section_info['title'];
        $section_href = $this->url->link('extension/probg_team/team');

        if ($category_id) {
            $category_info = $this->model_extension_probg_team_team->getCategory($category_id);

            if (!$category_info) {
                return '';
            }

            $default_title = $category_info['name'];
            $section_href = $this->url->link('extension/probg_team/category', 'probg_team_category_id=' . $category_id);
        }

        $data['heading_title'] = $custom_title !== '' ? $custom_title : $default_title;
        $data['text_read_more'] = $this->language->get('text_read_more');
        $data['section_href'] = $section_href;
        $data['column_class'] = 'col-lg-' . (int)(12 / $columns) . ' col-md-' . (int)(12 / min($columns, 4)) . ' col-sm-6 col-xs-12';
        $data['show_category'] = !empty($setting['show_category']);
        $data['show_city'] = !empty($setting['show_city']);
        $data['show_description'] = !empty($setting['show_description']);
        $data['members'] = array();

        $filter_data = array(
            'team_category_id' => $category_id,
            'sort' => $sort,
            'start' => 0,
            'limit' => $limit
        );

        foreach ($this->model_extension_probg_team_team->getMembers($filter_data) as $member) {
            if ($member['image'] && is_file(DIR_IMAGE . $member['image'])) {
                $thumb = $this->model_tool_image->resize($member['image'], $width, $height);
            } else {
                $thumb = $this->model_tool_image->resize('placeholder.png', $width, $height);
            }

            $description = trim(strip_tags(html_entity_decode($member['short_description'], ENT_QUOTES, 'UTF-8')));

            $data['members'][] = array(
                'name' => $member['name'],
                'category_name' => $member['category_name'],
                'city' => $member['city'],
                'description' => utf8_substr($description, 0, 140) . (utf8_strlen($description) > 140 ? '...' : ''),
                'thumb' => $thumb,
                'href' => $this->url->link('extension/probg_team/member', 'probg_team_category_id=' . (int)$member['team_category_id'] . '&probg_team_member_id=' . (int)$member['team_member_id'])
            );
        }

        if (!$data['members']) {
            return '';
        }

        return $this->load->view('extension/module/probg_team', $data);
    }
}
