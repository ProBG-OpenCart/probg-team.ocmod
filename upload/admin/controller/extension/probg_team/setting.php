<?php
class ControllerExtensionProbgTeamSetting extends Controller {
    private $error = array();

    public function index() {
        $data = $this->load->language('extension/module/probg_team');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addStyle('view/javascript/summernote/summernote.css');
        $this->document->addScript('view/javascript/summernote/summernote.js');
        $this->document->addScript('view/javascript/summernote/opencart.js');

        $this->load->model('setting/setting');
        $this->load->model('setting/store');
        $this->load->model('extension/module/probg_team');
        $this->model_extension_module_probg_team->upgrade();

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
            $settings = $this->request->post;

            // These values are stored in dedicated normalized tables, not duplicated in the setting table.
            unset($settings['module_probg_team_description'], $settings['module_probg_team_seo_url']);
            $settings['module_probg_team_schema_version'] = ModelExtensionModuleProbgTeam::SCHEMA_VERSION;

            $this->model_setting_setting->editSetting('module_probg_team', $settings);
            $this->model_extension_module_probg_team->saveDescriptions($descriptions);
            $this->model_extension_module_probg_team->saveSeoUrls($seo_urls, $descriptions);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/probg_team/setting', 'user_token=' . $this->session->data['user_token'], true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : (isset($this->session->data['error']) ? $this->session->data['error'] : '');
        unset($this->session->data['error']);
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);
        $data['error_title'] = isset($this->error['title']) ? $this->error['title'] : array();
        $data['error_seo_url'] = isset($this->error['seo_url']) ? $this->error['seo_url'] : array();

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/probg_team/setting', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/probg_team/setting', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['clear_cache'] = $this->url->link('extension/probg_team/setting/clearCache', 'user_token=' . $this->session->data['user_token'], true);
        $data['repair'] = $this->url->link('extension/probg_team/setting/repair', 'user_token=' . $this->session->data['user_token'], true);

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();

        $data['stores'] = array();
        $data['stores'][] = array(
            'store_id' => 0,
            'name' => $this->config->get('config_name')
        );

        foreach ($this->model_setting_store->getStores() as $store) {
            $data['stores'][] = array(
                'store_id' => (int)$store['store_id'],
                'name' => $store['name']
            );
        }

        if (isset($this->request->post['module_probg_team_description'])) {
            $data['module_probg_team_description'] = $this->request->post['module_probg_team_description'];
        } else {
            $data['module_probg_team_description'] = $this->model_extension_module_probg_team->getDescriptions();
        }

        if (isset($this->request->post['module_probg_team_seo_url'])) {
            $data['module_probg_team_seo_url'] = $this->request->post['module_probg_team_seo_url'];
        } else {
            $data['module_probg_team_seo_url'] = $this->model_extension_module_probg_team->getSeoUrls();
        }

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

        $data['sitemap_url'] = HTTPS_CATALOG . 'index.php?route=extension/feed/probg_team_sitemap';
        $data['google_sitemap_url'] = HTTPS_CATALOG . 'index.php?route=extension/feed/google_sitemap';
        $data['section_url'] = HTTPS_CATALOG . 'index.php?route=extension/probg_team/team';
        $data['diagnostics'] = $this->model_extension_module_probg_team->getDiagnostics();
        $data['module_version'] = ModelExtensionModuleProbgTeam::SCHEMA_VERSION;
        $data['user_token'] = $this->session->data['user_token'];
        $data['summernote'] = $this->config->get('config_admin_language');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/probg_team/setting', $data));
    }

    public function repair() {
        $this->load->language('extension/module/probg_team');

        if (!$this->user->hasPermission('modify', 'extension/probg_team/setting') && !$this->user->hasPermission('modify', 'extension/module/probg_team')) {
            $this->session->data['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('extension/module/probg_team');
            $this->model_extension_module_probg_team->upgrade(true);
            $this->model_extension_module_probg_team->rotateCacheVersion();
            $this->session->data['success'] = $this->language->get('text_repair_success');
        }

        $this->response->redirect($this->url->link('extension/probg_team/setting', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function clearCache() {
        $this->load->language('extension/module/probg_team');

        if (!$this->user->hasPermission('modify', 'extension/probg_team/setting') && !$this->user->hasPermission('modify', 'extension/module/probg_team')) {
            $this->session->data['error'] = $this->language->get('error_permission');
        } else {
            $this->load->model('extension/module/probg_team');
            $this->model_extension_module_probg_team->rotateCacheVersion();
            $this->session->data['success'] = $this->language->get('text_cache_cleared');
        }

        $this->response->redirect($this->url->link('extension/probg_team/setting', 'user_token=' . $this->session->data['user_token'], true));
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
        if (!$this->user->hasPermission('modify', 'extension/probg_team/setting') && !$this->user->hasPermission('modify', 'extension/module/probg_team')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $descriptions = isset($this->request->post['module_probg_team_description']) ? (array)$this->request->post['module_probg_team_description'] : array();
        $this->load->model('localisation/language');

        foreach ($this->model_localisation_language->getLanguages() as $language) {
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

        return !$this->error;
    }
}
