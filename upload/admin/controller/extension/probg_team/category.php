<?php
class ControllerExtensionProbgTeamCategory extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/probg_team/category');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->loadModels();
        $this->getList();
    }

    public function add() {
        $this->load->language('extension/probg_team/category');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->loadModels();

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->request->post = $this->model_extension_probg_team_category->prepareData($this->request->post, 0);
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_probg_team_category->addCategory($this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/probg_team/category', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->getForm();
    }

    public function edit() {
        $this->load->language('extension/probg_team/category');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->loadModels();

        $category_id = isset($this->request->get['team_category_id']) ? (int)$this->request->get['team_category_id'] : 0;

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->request->post = $this->model_extension_probg_team_category->prepareData($this->request->post, $category_id);
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_probg_team_category->editCategory($category_id, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/probg_team/category', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->getForm();
    }

    public function delete() {
        $this->load->language('extension/probg_team/category');
        $this->load->model('extension/probg_team/category');

        if (isset($this->request->post['selected']) && $this->validateDelete()) {
            foreach ((array)$this->request->post['selected'] as $category_id) {
                $this->model_extension_probg_team_category->deleteCategory((int)$category_id);
            }

            $this->session->data['success'] = $this->language->get('text_success');
        }

        $this->response->redirect($this->url->link('extension/probg_team/category', 'user_token=' . $this->session->data['user_token'], true));
    }

    protected function getList() {
        $data = $this->load->language('extension/probg_team/category');
        $filter_name = isset($this->request->get['filter_name']) ? $this->request->get['filter_name'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'c.sort_order';
        $order = (isset($this->request->get['order']) && $this->request->get['order'] == 'DESC') ? 'DESC' : 'ASC';
        $page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
        $limit = max(1, (int)$this->config->get('config_limit_admin'));
        $url = $this->buildListQuery(array('filter_name', 'filter_status', 'sort', 'order', 'page'));
        $filter_data = array(
            'filter_name' => $filter_name,
            'filter_status' => $filter_status,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit
        );

        $total = $this->model_extension_probg_team_category->getTotalCategories($filter_data);
        $data['categories'] = array();

        foreach ($this->model_extension_probg_team_category->getCategories($filter_data) as $result) {
            $data['categories'][] = array(
                'team_category_id' => (int)$result['team_category_id'],
                'name' => $result['name'],
                'member_total' => (int)$result['member_total'],
                'sort_order' => (int)$result['sort_order'],
                'status' => $result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
                'date_modified' => $result['date_modified'],
                'view' => HTTPS_CATALOG . 'index.php?route=extension/probg_team/category&probg_team_category_id=' . (int)$result['team_category_id'],
                'edit' => $this->url->link('extension/probg_team/category/edit', 'user_token=' . $this->session->data['user_token'] . '&team_category_id=' . (int)$result['team_category_id'] . $url, true)
            );
        }

        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/probg_team/category', 'user_token=' . $this->session->data['user_token'] . $url, true))
        );
        $data['add'] = $this->url->link('extension/probg_team/category/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
        $data['delete'] = $this->url->link('extension/probg_team/category/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
        $data['clear'] = $this->url->link('extension/probg_team/category', 'user_token=' . $this->session->data['user_token'], true);
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : (isset($this->session->data['error']) ? $this->session->data['error'] : '');
        unset($this->session->data['error']);
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);
        $data['selected'] = isset($this->request->post['selected']) ? (array)$this->request->post['selected'] : array();

        $base = 'user_token=' . $this->session->data['user_token'] . '&filter_name=' . urlencode($filter_name) . '&filter_status=' . urlencode($filter_status);
        $toggle_order = $order == 'ASC' ? 'DESC' : 'ASC';
        $data['sort_name'] = $this->url->link('extension/probg_team/category', $base . '&sort=cd.name&order=' . $toggle_order, true);
        $data['sort_sort_order'] = $this->url->link('extension/probg_team/category', $base . '&sort=c.sort_order&order=' . $toggle_order, true);
        $data['sort_status'] = $this->url->link('extension/probg_team/category', $base . '&sort=c.status&order=' . $toggle_order, true);

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('extension/probg_team/category', $base . '&sort=' . $sort . '&order=' . $order . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), $total ? (($page - 1) * $limit) + 1 : 0, min($page * $limit, $total), $total, ceil($total / $limit));
        $data['filter_name'] = $filter_name;
        $data['filter_status'] = $filter_status;
        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/probg_team/category_list', $data));
    }

    protected function getForm() {
        $data = $this->load->language('extension/probg_team/category');
        $this->document->addStyle('view/javascript/summernote/summernote.css');
        $this->document->addScript('view/javascript/summernote/summernote.js');
        $this->document->addScript('view/javascript/summernote/opencart.js');

        $category_id = isset($this->request->get['team_category_id']) ? (int)$this->request->get['team_category_id'] : 0;
        $category_info = $category_id ? $this->model_extension_probg_team_category->getCategory($category_id) : array();
        $data['text_form'] = $category_id ? $this->language->get('text_edit') : $this->language->get('text_add');
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_name'] = isset($this->error['name']) ? $this->error['name'] : array();
        $data['error_store'] = isset($this->error['store']) ? $this->error['store'] : '';
        $data['error_seo_url'] = isset($this->error['seo_url']) ? $this->error['seo_url'] : array();
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/probg_team/category', 'user_token=' . $this->session->data['user_token'], true))
        );
        $data['action'] = $category_id
            ? $this->url->link('extension/probg_team/category/edit', 'user_token=' . $this->session->data['user_token'] . '&team_category_id=' . $category_id, true)
            : $this->url->link('extension/probg_team/category/add', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/probg_team/category', 'user_token=' . $this->session->data['user_token'], true);
        $data['category_description'] = isset($this->request->post['category_description']) ? $this->request->post['category_description'] : ($category_id ? $this->model_extension_probg_team_category->getDescriptions($category_id) : array());
        $data['category_seo_url'] = isset($this->request->post['category_seo_url']) ? $this->request->post['category_seo_url'] : ($category_id ? $this->model_extension_probg_team_category->getSeoUrls($category_id) : array());

        foreach (array('sort_order' => 0, 'status' => 1) as $field => $default) {
            $data[$field] = isset($this->request->post[$field]) ? $this->request->post[$field] : (isset($category_info[$field]) ? $category_info[$field] : $default);
        }

        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();
        $this->load->model('setting/store');
        $data['stores'] = array(array('store_id' => 0, 'name' => $this->config->get('config_name')));

        foreach ($this->model_setting_store->getStores() as $store) {
            $data['stores'][] = array('store_id' => (int)$store['store_id'], 'name' => $store['name']);
        }

        if (isset($this->request->post['category_store'])) {
            $data['category_store'] = array_map('intval', (array)$this->request->post['category_store']);
        } elseif ($category_id) {
            $data['category_store'] = $this->model_extension_probg_team_category->getStores($category_id);
        } else {
            $data['category_store'] = array_column($data['stores'], 'store_id');
        }

        $data['view'] = $category_id ? HTTPS_CATALOG . 'index.php?route=extension/probg_team/category&probg_team_category_id=' . $category_id : '';
        $data['summernote'] = $this->config->get('config_admin_language');
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/probg_team/category_form', $data));
    }

    protected function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/probg_team/category')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $descriptions = isset($this->request->post['category_description']) ? $this->request->post['category_description'] : array();
        $this->load->model('localisation/language');

        foreach ($this->model_localisation_language->getLanguages() as $language) {
            $language_id = (int)$language['language_id'];
            $name = isset($descriptions[$language_id]['name']) ? trim($descriptions[$language_id]['name']) : '';

            if ((utf8_strlen($name) < 1) || (utf8_strlen($name) > 255)) {
                $this->error['name'][$language_id] = $this->language->get('error_name');
            }
        }

        $selected_stores = isset($this->request->post['category_store']) ? array_unique(array_map('intval', (array)$this->request->post['category_store'])) : array();
        $this->load->model('setting/store');
        $valid_stores = array(0);

        foreach ($this->model_setting_store->getStores() as $store) {
            $valid_stores[] = (int)$store['store_id'];
        }

        if (!$selected_stores || array_diff($selected_stores, $valid_stores)) {
            $this->error['store'] = $this->language->get('error_store');
        }

        $category_id = isset($this->request->get['team_category_id']) ? (int)$this->request->get['team_category_id'] : 0;
        $exclude_query = 'probg_team_category_id=' . $category_id;

        if (!empty($this->request->post['category_seo_url'])) {
            foreach ($this->request->post['category_seo_url'] as $store_id => $language_keywords) {
                foreach ($language_keywords as $language_id => $keyword) {
                    $keyword = trim($keyword);

                    if ($keyword !== '' && !preg_match('/^[\p{L}\p{N}_-]+$/u', $keyword)) {
                        $this->error['seo_url'][$store_id][$language_id] = $this->language->get('error_seo_url_format');
                    } elseif ($keyword !== '' && $this->model_extension_probg_team_category->isSeoKeywordUsed($keyword, $store_id, $language_id, $exclude_query)) {
                        $this->error['seo_url'][$store_id][$language_id] = $this->language->get('error_seo_url');
                    }
                }
            }
        }

        return !$this->error;
    }

    protected function validateDelete() {
        if (!$this->user->hasPermission('modify', 'extension/probg_team/category')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        foreach ((array)$this->request->post['selected'] as $category_id) {
            if ($this->model_extension_probg_team_category->getTotalMembersByCategoryId((int)$category_id)) {
                $this->error['warning'] = $this->language->get('error_member');
                break;
            }
        }

        if ($this->error) {
            $this->session->data['error'] = $this->error['warning'];
        }

        return !$this->error;
    }

    private function loadModels() {
        $this->load->model('extension/module/probg_team');
        $this->model_extension_module_probg_team->upgrade();
        $this->load->model('extension/probg_team/category');
    }

    private function buildListQuery($keys) {
        $url = '';

        foreach ($keys as $key) {
            if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
                $url .= '&' . $key . '=' . urlencode($this->request->get[$key]);
            }
        }

        return $url;
    }
}
