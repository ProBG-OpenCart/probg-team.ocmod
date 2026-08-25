<?php
class ControllerExtensionModuleProbgTeam extends Controller {
    private static $rendering_instances = array();

    public function index($setting = array()) {
        if (!$this->config->get('module_probg_team_status')) {
            return '';
        }

        $setting = is_array($setting) ? $setting : array();

        if (isset($setting['status']) && !(int)$setting['status']) {
            return '';
        }

        $guard_key = md5(serialize($setting));
        if (isset(self::$rendering_instances[$guard_key])) {
            return '';
        }

        self::$rendering_instances[$guard_key] = true;
        $output = $this->renderInstance($setting);
        unset(self::$rendering_instances[$guard_key]);

        return $output;
    }

    private function renderInstance($setting) {
        if (isset($setting['probg_team_type']) && $setting['probg_team_type'] === 'menu') {
            return $this->menuModule($setting);
        }

        if (isset($setting['probg_team_type']) && $setting['probg_team_type'] === 'members') {
            return $this->membersModule($setting);
        }

        // Explicit typed instances are the only supported Layout output after migration.
        if ($this->config->get('module_probg_team_instances_migrated')) {
            return '';
        }

        // Backward-compatible fallback for a bare v0.9.x Layout assignment
        // before the one-time instance migration has been completed.
        return $this->membersModule(array(
            'probg_team_type' => 'members',
            'limit' => 4,
            'columns' => 4,
            'sort' => 'sort_order',
            'show_category' => 1,
            'show_city' => 0,
            'show_description' => 0,
            'status' => 1
        ));
    }

    private function membersModule($setting) {
        $this->load->language('extension/module/probg_team');
        $this->load->model('extension/probg_team/team');
        $this->load->model('tool/image');
        $this->document->addStyle('catalog/view/javascript/probg_team/probg_team.css');

        $section_info = $this->model_extension_probg_team_team->getSectionDescription();

        if (!$section_info) {
            return '';
        }

        $language_id = (int)$this->config->get('config_language_id');
        $limit = isset($setting['limit']) ? (int)$setting['limit'] : 4;
        $limit = max(1, min(1000, $limit));
        $columns = isset($setting['columns']) ? (int)$setting['columns'] : 4;

        if (!in_array($columns, array(1, 2, 3, 4, 6), true)) {
            $columns = 4;
        }

        $width = max(1, (int)$this->config->get('module_probg_team_list_width'));
        $height = max(1, (int)$this->config->get('module_probg_team_list_height'));
        $category_id = isset($setting['team_category_id']) ? max(0, (int)$setting['team_category_id']) : 0;
        $sort = isset($setting['sort']) ? $setting['sort'] : 'sort_order';

        if (!in_array($sort, array('sort_order', 'name', 'date_added'), true)) {
            $sort = 'sort_order';
        }

        $titles = isset($setting['title']) && is_array($setting['title']) ? $setting['title'] : array();
        $custom_title = isset($titles[$language_id]) ? trim($titles[$language_id]) : '';
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
            $member_image = $this->getSafeImagePath($member['image']);
            $thumb = $member_image
                ? $this->model_tool_image->resize($member_image, $width, $height)
                : $this->model_tool_image->resize('placeholder.png', $width, $height);

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

        return $this->load->view('extension/module/probg_team_members', $data);
    }

    private function menuModule($setting) {
        $this->load->language('extension/module/probg_team_menu');
        $this->load->model('extension/probg_team/team');
        $this->document->addStyle('catalog/view/javascript/probg_team/probg_team.css');

        $section_info = $this->model_extension_probg_team_team->getSectionDescription();

        if (!$section_info) {
            return '';
        }

        $language_id = (int)$this->config->get('config_language_id');
        $category_id = isset($setting['team_category_id']) ? max(0, (int)$setting['team_category_id']) : 0;
        $limit = isset($setting['limit']) ? (int)$setting['limit'] : 10;
        $limit = max(1, min(1000, $limit));
        $titles = isset($setting['title']) && is_array($setting['title']) ? $setting['title'] : array();
        $custom_title = isset($titles[$language_id]) ? trim($titles[$language_id]) : '';
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
