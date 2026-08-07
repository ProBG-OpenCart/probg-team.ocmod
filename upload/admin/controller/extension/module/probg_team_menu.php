<?php
class ControllerExtensionModuleProbgTeamMenu extends Controller {
    private $error = array();

    public function index() {
        $data = $this->load->language('extension/module/probg_team_menu');
        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/module');
        $this->load->model('extension/probg_team/category');
        $this->load->model('localisation/language');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $post_data = $this->normalizePostData($this->request->post);

            if (!isset($this->request->get['module_id'])) {
                $this->model_setting_module->addModule('probg_team_menu', $post_data);
            } else {
                $this->model_setting_module->editModule((int)$this->request->get['module_id'], $post_data);
            }

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_name'] = isset($this->error['name']) ? $this->error['name'] : '';
        $data['error_limit'] = isset($this->error['limit']) ? $this->error['limit'] : '';
        $data['error_category'] = isset($this->error['category']) ? $this->error['category'] : '';

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
            'href' => $this->url->link('extension/module/probg_team_menu', 'user_token=' . $this->session->data['user_token'] . (isset($this->request->get['module_id']) ? '&module_id=' . (int)$this->request->get['module_id'] : ''), true)
        );

        if (!isset($this->request->get['module_id'])) {
            $data['action'] = $this->url->link('extension/module/probg_team_menu', 'user_token=' . $this->session->data['user_token'], true);
        } else {
            $data['action'] = $this->url->link('extension/module/probg_team_menu', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . (int)$this->request->get['module_id'], true);
        }

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['settings'] = $this->url->link('extension/probg_team/setting', 'user_token=' . $this->session->data['user_token'], true);

        if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
            $module_info = $this->model_setting_module->getModule((int)$this->request->get['module_id']);
        } else {
            $module_info = array();
        }

        $defaults = array(
            'name' => '',
            'title' => array(),
            'team_category_id' => 0,
            'limit' => 10,
            'status' => 1
        );

        foreach ($defaults as $key => $default) {
            if (isset($this->request->post[$key])) {
                $data[$key] = $this->request->post[$key];
            } elseif (isset($module_info[$key])) {
                $data[$key] = $module_info[$key];
            } else {
                $data[$key] = $default;
            }
        }

        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['categories'] = $this->model_extension_probg_team_category->getCategories(array(
            'sort' => 'cd.name',
            'order' => 'ASC',
            'start' => 0,
            'limit' => 1000
        ));

        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/probg_team_menu', $data));
    }

    public function install() {
        $this->load->model('user/user_group');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/probg_team_menu');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/probg_team_menu');
    }

    public function uninstall() {
        $this->load->model('setting/module');
        $this->model_setting_module->deleteModulesByCode('probg_team_menu');
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/probg_team_menu')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $name = isset($this->request->post['name']) ? trim($this->request->post['name']) : '';

        if ((utf8_strlen($name) < 3) || (utf8_strlen($name) > 64)) {
            $this->error['name'] = $this->language->get('error_name');
        }

        $limit = isset($this->request->post['limit']) ? (int)$this->request->post['limit'] : 0;

        if ($limit < 1 || $limit > 1000) {
            $this->error['limit'] = $this->language->get('error_limit');
        }

        $team_category_id = isset($this->request->post['team_category_id']) ? (int)$this->request->post['team_category_id'] : 0;

        if ($team_category_id && !$this->model_extension_probg_team_category->getCategory($team_category_id)) {
            $this->error['category'] = $this->language->get('error_category');
        }

        return !$this->error;
    }

    private function normalizePostData($data) {
        $normalized = array();
        $normalized['name'] = isset($data['name']) ? trim($data['name']) : '';
        $normalized['title'] = array();

        if (!empty($data['title']) && is_array($data['title'])) {
            foreach ($data['title'] as $language_id => $title) {
                $normalized['title'][(int)$language_id] = trim($title);
            }
        }

        $normalized['team_category_id'] = isset($data['team_category_id']) ? max(0, (int)$data['team_category_id']) : 0;
        $normalized['limit'] = isset($data['limit']) ? min(1000, max(1, (int)$data['limit'])) : 10;
        $normalized['status'] = !empty($data['status']) ? 1 : 0;

        return $normalized;
    }
}
