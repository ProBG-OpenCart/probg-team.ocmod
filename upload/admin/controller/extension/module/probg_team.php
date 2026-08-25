<?php
class ControllerExtensionModuleProbgTeam extends Controller {
    const VERSION = '1.0.1';

    private $error = array();

    public function index() {
        $data = $this->load->language('extension/module/probg_team');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        $this->load->model('setting/store');
        $this->load->model('setting/module');
        $this->load->model('extension/module/probg_team');
        $this->load->model('extension/probg_team/category');
        $this->load->model('extension/probg_team/member');

        $this->model_extension_module_probg_team->upgrade();

        if ($this->user->hasPermission('modify', 'extension/module/probg_team')) {
            $this->grantPermissions();
        }

        $this->migrateModuleInstances();

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->request->post = $this->model_extension_module_probg_team->prepareData($this->request->post);

            foreach (array(
                'module_probg_team_limit',
                'module_probg_team_search_limit',
                'module_probg_team_list_width',
                'module_probg_team_list_height',
                'module_probg_team_member_width',
                'module_probg_team_member_height',
                'module_probg_team_gallery_width',
                'module_probg_team_gallery_height'
            ) as $numeric_key) {
                $this->request->post[$numeric_key] = $this->normalizePositiveInteger(
                    isset($this->request->post[$numeric_key]) ? $this->request->post[$numeric_key] : 1,
                    1,
                    5000
                );
            }
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $descriptions = isset($this->request->post['module_probg_team_description']) ? $this->request->post['module_probg_team_description'] : array();
            $seo_urls = isset($this->request->post['module_probg_team_seo_url']) ? $this->request->post['module_probg_team_seo_url'] : array();
            $blocks = isset($this->request->post['probg_team_blocks']) ? (array)$this->request->post['probg_team_blocks'] : array();
            $menus = isset($this->request->post['probg_team_menus']) ? (array)$this->request->post['probg_team_menus'] : array();

            $this->syncInstances('members', $blocks);
            $this->syncInstances('menu', $menus);

            $settings = $this->request->post;
            unset(
                $settings['module_probg_team_description'],
                $settings['module_probg_team_seo_url'],
                $settings['probg_team_blocks'],
                $settings['probg_team_menus']
            );

            $settings['module_probg_team_schema_version'] = ModelExtensionModuleProbgTeam::SCHEMA_VERSION;
            $settings['module_probg_team_version'] = self::VERSION;
            $settings['module_probg_team_instances_migrated'] = 1;

            $this->model_setting_setting->editSetting('module_probg_team', $settings);
            $this->model_extension_module_probg_team->saveDescriptions($descriptions);
            $this->model_extension_module_probg_team->saveSeoUrls($seo_urls, $descriptions);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/probg_team', 'user_token=' . $this->session->data['user_token'], true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : (isset($this->session->data['error']) ? $this->session->data['error'] : '');
        unset($this->session->data['error']);
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);

        $data['error_title'] = isset($this->error['title']) ? $this->error['title'] : array();
        $data['error_seo_url'] = isset($this->error['seo_url']) ? $this->error['seo_url'] : array();
        $data['error_block_name'] = isset($this->error['block_name']) ? $this->error['block_name'] : array();
        $data['error_block_limit'] = isset($this->error['block_limit']) ? $this->error['block_limit'] : array();
        $data['error_block_title'] = isset($this->error['block_title']) ? $this->error['block_title'] : array();
        $data['error_menu_name'] = isset($this->error['menu_name']) ? $this->error['menu_name'] : array();
        $data['error_menu_limit'] = isset($this->error['menu_limit']) ? $this->error['menu_limit'] : array();
        $data['error_menu_title'] = isset($this->error['menu_title']) ? $this->error['menu_title'] : array();

        $data['breadcrumbs'] = array(
            array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
            ),
            array(
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
            ),
            array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/module/probg_team', 'user_token=' . $this->session->data['user_token'], true)
            )
        );

        $data['action'] = $this->url->link('extension/module/probg_team', 'user_token=' . $this->session->data['user_token'], true);
        $data['settings_url'] = $data['action'];
        $data['categories_url'] = $this->url->link('extension/probg_team/category', 'user_token=' . $this->session->data['user_token'], true);
        $data['members_url'] = $this->url->link('extension/probg_team/member', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['clear_cache'] = $this->url->link('extension/module/probg_team/clearCache', 'user_token=' . $this->session->data['user_token'], true);
        $data['repair'] = $this->url->link('extension/module/probg_team/repair', 'user_token=' . $this->session->data['user_token'], true);

        $data['total_categories'] = $this->model_extension_probg_team_category->getTotalCategories(array());
        $data['total_members'] = $this->model_extension_probg_team_member->getTotalMembers(array());

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $data['stores'] = array(
            array('store_id' => 0, 'name' => $this->config->get('config_name'))
        );

        foreach ($this->model_setting_store->getStores() as $store) {
            $data['stores'][] = array(
                'store_id' => (int)$store['store_id'],
                'name' => $store['name']
            );
        }

        $data['module_probg_team_description'] = isset($this->request->post['module_probg_team_description'])
            ? $this->request->post['module_probg_team_description']
            : $this->model_extension_module_probg_team->getDescriptions();

        $data['module_probg_team_seo_url'] = isset($this->request->post['module_probg_team_seo_url'])
            ? $this->request->post['module_probg_team_seo_url']
            : $this->model_extension_module_probg_team->getSeoUrls();

        $setting_defaults = array(
            'module_probg_team_status' => 0,
            'module_probg_team_limit' => 12,
            'module_probg_team_show_empty_categories' => 0,
            'module_probg_team_show_telephone' => 1,
            'module_probg_team_show_city' => 1,
            'module_probg_team_show_working_hours' => 1,
            'module_probg_team_show_website' => 1,
            'module_probg_team_show_social' => 1,
            'module_probg_team_open_graph_status' => 1,
            'module_probg_team_schema_status' => 1,
            'module_probg_team_cache_status' => 1,
            'module_probg_team_search_status' => 1,
            'module_probg_team_search_limit' => 6,
            'module_probg_team_sitemap_status' => 1,
            'module_probg_team_list_width' => 400,
            'module_probg_team_list_height' => 400,
            'module_probg_team_member_width' => 800,
            'module_probg_team_member_height' => 800,
            'module_probg_team_gallery_width' => 250,
            'module_probg_team_gallery_height' => 250
        );

        foreach ($setting_defaults as $key => $default) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } else {
                $value = $this->config->get($key);
                $data[$key] = ($value !== null) ? $value : $default;
            }
        }

        $data['categories'] = $this->model_extension_probg_team_category->getCategories(array(
            'sort' => 'cd.name',
            'order' => 'ASC',
            'start' => 0,
            'limit' => 1000
        ));

        $block_rows = isset($this->request->post['probg_team_blocks'])
            ? (array)$this->request->post['probg_team_blocks']
            : $this->getInstances('members');
        $data['probg_team_blocks'] = array();
        foreach ($block_rows as $block) {
            $data['probg_team_blocks'][] = $this->normalizeInstance('members', $block, isset($block['module_id']) ? (int)$block['module_id'] : 0);
        }

        $menu_rows = isset($this->request->post['probg_team_menus'])
            ? (array)$this->request->post['probg_team_menus']
            : $this->getInstances('menu');
        $data['probg_team_menus'] = array();
        foreach ($menu_rows as $menu) {
            $data['probg_team_menus'][] = $this->normalizeInstance('menu', $menu, isset($menu['module_id']) ? (int)$menu['module_id'] : 0);
        }

        $data['sitemap_url'] = HTTPS_CATALOG . 'index.php?route=extension/feed/probg_team_sitemap';
        $data['google_sitemap_url'] = HTTPS_CATALOG . 'index.php?route=extension/feed/google_sitemap';
        $data['section_url'] = HTTPS_CATALOG . 'index.php?route=extension/probg_team/team';
        $data['diagnostics'] = $this->model_extension_module_probg_team->getDiagnostics();
        $data['module_version'] = self::VERSION;
        $data['user_token'] = $this->session->data['user_token'];
        $data['summernote'] = $this->config->get('config_admin_language');

        $this->document->addStyle('view/javascript/summernote/summernote.css');
        $this->document->addScript('view/javascript/summernote/summernote.js');
        $this->document->addScript('view/javascript/summernote/opencart.js');
        $this->document->addStyle('view/stylesheet/probg_team_instances.css');
        $this->document->addScript('view/javascript/probg_team_instances.js');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/probg_team', $data));
    }

    public function install() {
        $this->load->model('extension/module/probg_team');
        $this->model_extension_module_probg_team->install();
        $this->load->model('setting/setting');
        $this->load->model('setting/module');

        $settings = $this->model_setting_setting->getSetting('module_probg_team');
        $settings['module_probg_team_version'] = self::VERSION;
        $settings['module_probg_team_instances_migrated'] = 1;
        $this->model_setting_setting->editSetting('module_probg_team', $settings);

        $this->grantPermissions();
        $this->migrateModuleInstances(true);
        $this->ensureDefaultMemberInstance();
    }

    public function uninstall() {
        $this->load->model('setting/module');

        foreach ($this->model_setting_module->getModulesByCode('probg_team') as $module) {
            $module_id = isset($module['module_id']) ? (int)$module['module_id'] : 0;
            if ($module_id > 0) {
                $this->cleanupLayoutModuleReference($module_id);
                $this->model_setting_module->deleteModule($module_id);
            }
        }

        foreach (array('probg_team_members', 'probg_team_menu') as $legacy_code) {
            foreach ($this->model_setting_module->getModulesByCode($legacy_code) as $module) {
                $module_id = isset($module['module_id']) ? (int)$module['module_id'] : 0;
                if ($module_id > 0) {
                    $this->cleanupLegacyLayoutModuleReference($legacy_code, $module_id);
                    $this->model_setting_module->deleteModule($module_id);
                }
            }
        }

        $this->load->model('extension/module/probg_team');
        $this->model_extension_module_probg_team->uninstall();

        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_probg_team');

        $this->db->query("DELETE FROM `" . DB_PREFIX . "extension` WHERE `type` = 'module' AND `code` IN ('probg_team_members', 'probg_team_menu')");
    }

    public function repair() {
        $this->load->language('extension/module/probg_team');

        if (!$this->user->hasPermission('modify', 'extension/module/probg_team') && !$this->user->hasPermission('modify', 'extension/probg_team/setting')) {
            $this->session->data['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('extension/module/probg_team');
            $this->model_extension_module_probg_team->upgrade(true);
            $this->model_extension_module_probg_team->rotateCacheVersion();
            $this->load->model('setting/module');
            $this->load->model('setting/setting');
            $this->migrateModuleInstances(true);
            $this->session->data['success'] = $this->language->get('text_repair_success');
        }

        $this->response->redirect($this->url->link('extension/module/probg_team', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function clearCache() {
        $this->load->language('extension/module/probg_team');

        if (!$this->user->hasPermission('modify', 'extension/module/probg_team') && !$this->user->hasPermission('modify', 'extension/probg_team/setting')) {
            $this->session->data['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('extension/module/probg_team');
            $this->model_extension_module_probg_team->rotateCacheVersion();
            $this->session->data['success'] = $this->language->get('text_cache_cleared');
        }

        $this->response->redirect($this->url->link('extension/module/probg_team', 'user_token=' . $this->session->data['user_token'], true));
    }

    private function migrateModuleInstances($force = false) {
        $this->load->model('setting/setting');
        $this->load->model('setting/module');

        $settings = $this->model_setting_setting->getSetting('module_probg_team');
        $migrated = !empty($settings['module_probg_team_instances_migrated']);

        if (!$migrated || $force) {
            foreach (array('probg_team_members' => 'members', 'probg_team_menu' => 'menu') as $legacy_code => $type) {
                foreach ($this->model_setting_module->getModulesByCode($legacy_code) as $module) {
                    $module_id = (int)$module['module_id'];
                    $setting = isset($module['setting']) ? json_decode($module['setting'], true) : array();
                    if (!is_array($setting)) {
                        $setting = array();
                    }

                    $setting = $this->normalizeInstance($type, $setting, $module_id);
                    unset($setting['module_id']);

                    $this->db->query("UPDATE `" . DB_PREFIX . "module` SET `code` = 'probg_team', `setting` = '" . $this->db->escape(json_encode($setting)) . "' WHERE module_id = '" . $module_id . "'");
                    $this->migrateLayoutReference($legacy_code, $module_id);
                }
            }

            foreach ($this->model_setting_module->getModulesByCode('probg_team') as $module) {
                $module_id = (int)$module['module_id'];
                $setting = isset($module['setting']) ? json_decode($module['setting'], true) : array();
                if (!is_array($setting)) {
                    $setting = array();
                }

                $type = isset($setting['probg_team_type']) && $setting['probg_team_type'] === 'menu' ? 'menu' : 'members';
                $setting = $this->normalizeInstance($type, $setting, $module_id);
                unset($setting['module_id']);

                $this->db->query("UPDATE `" . DB_PREFIX . "module` SET `setting` = '" . $this->db->escape(json_encode($setting)) . "' WHERE module_id = '" . $module_id . "'");
            }

            $this->db->query("DELETE FROM `" . DB_PREFIX . "extension` WHERE `type` = 'module' AND `code` IN ('probg_team_members', 'probg_team_menu')");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'module_probg_team' AND `key` = 'module_probg_team_admin_structure_version'");

            $settings['module_probg_team_instances_migrated'] = 1;
            unset($settings['module_probg_team_admin_structure_version']);
            $settings['module_probg_team_version'] = self::VERSION;
            $this->model_setting_setting->editSetting('module_probg_team', $settings);
        }

        $default_id = $this->ensureDefaultMemberInstance();
        if ($default_id > 0) {
            $column = $this->layoutModuleColumn();
            if ($column !== '') {
                $this->db->query("UPDATE `" . DB_PREFIX . "layout_module` SET `" . $column . "` = 'probg_team." . $default_id . "' WHERE `" . $column . "` = 'probg_team'");
            }
        }

        $this->grantPermissions();
    }

    private function ensureDefaultMemberInstance() {
        foreach ($this->getInstances('members') as $instance) {
            return (int)$instance['module_id'];
        }

        $this->load->model('setting/module');
        $this->model_setting_module->addModule('probg_team', array(
            'name' => 'ProBG Team - Members',
            'probg_team_type' => 'members',
            'title' => array(),
            'team_category_id' => 0,
            'limit' => 4,
            'columns' => 4,
            'sort' => 'sort_order',
            'show_category' => 1,
            'show_city' => 0,
            'show_description' => 0,
            'status' => 1
        ));

        return (int)$this->db->getLastId();
    }

    private function getInstances($type) {
        $instances = array();

        foreach ($this->model_setting_module->getModulesByCode('probg_team') as $module) {
            $module_id = (int)$module['module_id'];
            $setting = isset($module['setting']) ? json_decode($module['setting'], true) : array();

            if (!is_array($setting)) {
                continue;
            }

            $instance_type = isset($setting['probg_team_type']) ? $setting['probg_team_type'] : 'members';
            if ($instance_type !== $type) {
                continue;
            }

            $instances[] = $this->normalizeInstance($type, $setting, $module_id);
        }

        return $instances;
    }

    private function normalizeInstance($type, $instance, $module_id = 0) {
        $instance = is_array($instance) ? $instance : array();

        if ($type === 'menu') {
            $defaults = array(
                'name' => 'ProBG Team Menu',
                'probg_team_type' => 'menu',
                'title' => array(),
                'team_category_id' => 0,
                'limit' => 10,
                'status' => 1
            );
        } else {
            $defaults = array(
                'name' => 'ProBG Team - Members',
                'probg_team_type' => 'members',
                'title' => array(),
                'team_category_id' => 0,
                'limit' => 4,
                'columns' => 4,
                'sort' => 'sort_order',
                'show_category' => 1,
                'show_city' => 0,
                'show_description' => 0,
                'status' => 1
            );
        }

        $instance = array_merge($defaults, $instance);
        $instance['module_id'] = $module_id ?: (isset($instance['module_id']) ? (int)$instance['module_id'] : 0);
        $instance['probg_team_type'] = $type;
        $instance['name'] = trim((string)$instance['name']);
        if ($instance['name'] === '') {
            $instance['name'] = $defaults['name'];
        }
        $instance['title'] = is_array($instance['title']) ? $instance['title'] : array();
        $instance['team_category_id'] = max(0, (int)$instance['team_category_id']);
        $instance['limit'] = max(1, min(1000, (int)$instance['limit']));
        $instance['status'] = !empty($instance['status']) ? 1 : 0;

        if ($type === 'members') {
            $instance['columns'] = in_array((int)$instance['columns'], array(1, 2, 3, 4, 6), true) ? (int)$instance['columns'] : 4;
            $instance['sort'] = in_array($instance['sort'], array('sort_order', 'name', 'date_added'), true) ? $instance['sort'] : 'sort_order';
            $instance['show_category'] = !empty($instance['show_category']) ? 1 : 0;
            $instance['show_city'] = !empty($instance['show_city']) ? 1 : 0;
            $instance['show_description'] = !empty($instance['show_description']) ? 1 : 0;
        }

        return $instance;
    }

    private function syncInstances($type, $rows) {
        $existing = array();
        foreach ($this->getInstances($type) as $instance) {
            $existing[(int)$instance['module_id']] = true;
        }

        $keep = array();

        foreach ((array)$rows as $row) {
            $module_id = isset($row['module_id']) ? (int)$row['module_id'] : 0;
            $normalized = $this->normalizeInstance($type, $row, $module_id);
            $data = $normalized;
            unset($data['module_id']);

            if ($module_id > 0 && isset($existing[$module_id])) {
                $this->model_setting_module->editModule($module_id, $data);
            } else {
                $this->model_setting_module->addModule('probg_team', $data);
                $module_id = (int)$this->db->getLastId();
            }

            if ($module_id > 0) {
                $keep[$module_id] = true;
            }
        }

        foreach ($existing as $module_id => $unused) {
            if (!isset($keep[$module_id])) {
                $this->cleanupLayoutModuleReference($module_id);
                $this->model_setting_module->deleteModule($module_id);
            }
        }
    }

    private function migrateLayoutReference($legacy_code, $module_id) {
        $column = $this->layoutModuleColumn();
        if ($column === '') {
            return;
        }

        $old = $legacy_code . '.' . (int)$module_id;
        $new = 'probg_team.' . (int)$module_id;
        $this->db->query("UPDATE `" . DB_PREFIX . "layout_module` SET `" . $column . "` = '" . $this->db->escape($new) . "' WHERE `" . $column . "` = '" . $this->db->escape($old) . "'");
    }

    private function cleanupLayoutModuleReference($module_id) {
        $column = $this->layoutModuleColumn();
        if ($column !== '') {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "layout_module` WHERE `" . $column . "` = 'probg_team." . (int)$module_id . "'");
        }
    }

    private function cleanupLegacyLayoutModuleReference($legacy_code, $module_id) {
        $column = $this->layoutModuleColumn();
        if ($column !== '') {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "layout_module` WHERE `" . $column . "` = '" . $this->db->escape($legacy_code . '.' . (int)$module_id) . "'");
        }
    }

    private function layoutModuleColumn() {
        $code = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "layout_module` LIKE 'code'");
        if ($code->num_rows) {
            return 'code';
        }

        $module = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "layout_module` LIKE 'module'");
        return $module->num_rows ? 'module' : '';
    }

    private function grantPermissions() {
        $this->load->model('user/user_group');
        $group_id = (int)$this->user->getGroupId();
        $routes = array(
            'extension/module/probg_team',
            'extension/probg_team/setting',
            'extension/probg_team/category',
            'extension/probg_team/member'
        );

        foreach ($routes as $route) {
            $this->model_user_user_group->addPermission($group_id, 'access', $route);
            $this->model_user_user_group->addPermission($group_id, 'modify', $route);
        }
    }

    private function normalizePositiveInteger($value, $minimum, $maximum) {
        $value = (int)$value;

        if ($value < $minimum) {
            return (int)$minimum;
        }

        if ($value > $maximum) {
            return (int)$maximum;
        }

        return $value;
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/probg_team') && !$this->user->hasPermission('modify', 'extension/probg_team/setting')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $descriptions = isset($this->request->post['module_probg_team_description']) ? (array)$this->request->post['module_probg_team_description'] : array();
        $blocks = isset($this->request->post['probg_team_blocks']) ? (array)$this->request->post['probg_team_blocks'] : array();
        $menus = isset($this->request->post['probg_team_menus']) ? (array)$this->request->post['probg_team_menus'] : array();

        $this->load->model('localisation/language');
        $languages = $this->model_localisation_language->getLanguages();

        foreach ($languages as $language) {
            $language_id = (int)$language['language_id'];
            $title = isset($descriptions[0][$language_id]['title']) ? trim($descriptions[0][$language_id]['title']) : '';

            if ((utf8_strlen($title) < 1) || (utf8_strlen($title) > 255)) {
                $this->error['title'][0][$language_id] = $this->language->get('error_title');
            }
        }

        foreach ($descriptions as $store_id => $language_descriptions) {
            if ((int)$store_id === 0) {
                continue;
            }

            foreach ((array)$language_descriptions as $language_id => $description) {
                $title = isset($description['title']) ? trim($description['title']) : '';
                if (utf8_strlen($title) > 255) {
                    $this->error['title'][$store_id][$language_id] = $this->language->get('error_title');
                }
            }
        }

        if (!empty($this->request->post['module_probg_team_seo_url'])) {
            foreach ($this->request->post['module_probg_team_seo_url'] as $store_id => $language_keywords) {
                foreach ($language_keywords as $language_id => $keyword) {
                    $keyword = trim($keyword);

                    if ($keyword && !preg_match('/^[\p{L}\p{N}_-]+$/u', $keyword)) {
                        $this->error['seo_url'][$store_id][$language_id] = $this->language->get('error_seo_url_format');
                    } elseif ($keyword && $this->model_extension_module_probg_team->isSeoKeywordUsed($keyword, (int)$store_id, (int)$language_id, 'probg_team_section=1')) {
                        $this->error['seo_url'][$store_id][$language_id] = $this->language->get('error_seo_url');
                    }
                }
            }
        }

        foreach ($blocks as $row => $block) {
            $name = isset($block['name']) ? trim($block['name']) : '';
            if (utf8_strlen($name) < 3 || utf8_strlen($name) > 64) {
                $this->error['block_name'][$row] = $this->language->get('error_name_block');
            }

            $limit = isset($block['limit']) ? (int)$block['limit'] : 0;
            if ($limit < 1 || $limit > 1000) {
                $this->error['block_limit'][$row] = $this->language->get('error_limit_block');
            }

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                $title = isset($block['title'][$language_id]) ? trim($block['title'][$language_id]) : '';
                if (utf8_strlen($title) > 255) {
                    $this->error['block_title'][$row][$language_id] = $this->language->get('error_title_block');
                }
            }
        }

        foreach ($menus as $row => $menu) {
            $name = isset($menu['name']) ? trim($menu['name']) : '';
            if (utf8_strlen($name) < 3 || utf8_strlen($name) > 64) {
                $this->error['menu_name'][$row] = $this->language->get('error_menu_name');
            }

            $limit = isset($menu['limit']) ? (int)$menu['limit'] : 0;
            if ($limit < 1 || $limit > 1000) {
                $this->error['menu_limit'][$row] = $this->language->get('error_menu_limit');
            }

            foreach ($languages as $language) {
                $language_id = (int)$language['language_id'];
                $title = isset($menu['title'][$language_id]) ? trim($menu['title'][$language_id]) : '';
                if (utf8_strlen($title) > 255) {
                    $this->error['menu_title'][$row][$language_id] = $this->language->get('error_menu_title');
                }
            }
        }

        return !$this->error;
    }
}
