<?php
class ControllerExtensionProbgTeamMember extends Controller {
    private $error = array();

    public function index() {
        $this->load->language('extension/probg_team/member');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->loadModels();
        $this->getList();
    }

    public function add() {
        $this->load->language('extension/probg_team/member');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->loadModels();

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->request->post = $this->model_extension_probg_team_member->prepareData($this->request->post, 0);
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_probg_team_member->addMember($this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/probg_team/member', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->getForm();
    }

    public function edit() {
        $this->load->language('extension/probg_team/member');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->loadModels();

        $member_id = isset($this->request->get['team_member_id']) ? (int)$this->request->get['team_member_id'] : 0;

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->request->post = $this->model_extension_probg_team_member->prepareData($this->request->post, $member_id);
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
            $this->model_extension_probg_team_member->editMember($member_id, $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/probg_team/member', 'user_token=' . $this->session->data['user_token'], true));
        }

        $this->getForm();
    }

    public function delete() {
        $this->load->language('extension/probg_team/member');
        $this->load->model('extension/probg_team/member');

        if (isset($this->request->post['selected'])) {
            if ($this->user->hasPermission('modify', 'extension/probg_team/member')) {
                foreach ((array)$this->request->post['selected'] as $member_id) {
                    $this->model_extension_probg_team_member->deleteMember((int)$member_id);
                }

                $this->session->data['success'] = $this->language->get('text_success');
            } else {
                $this->session->data['error'] = $this->language->get('error_permission');
            }
        }

        $this->response->redirect($this->url->link('extension/probg_team/member', 'user_token=' . $this->session->data['user_token'], true));
    }

    protected function getList() {
        $data = $this->load->language('extension/probg_team/member');
        $filter_member_id = isset($this->request->get['filter_member_id']) ? (int)$this->request->get['filter_member_id'] : '';
        $filter_name = isset($this->request->get['filter_name']) ? $this->request->get['filter_name'] : '';
        $filter_category_id = isset($this->request->get['filter_category_id']) ? $this->request->get['filter_category_id'] : '';
        $filter_city = isset($this->request->get['filter_city']) ? $this->request->get['filter_city'] : '';
        $filter_status = isset($this->request->get['filter_status']) ? $this->request->get['filter_status'] : '';
        $sort = isset($this->request->get['sort']) ? $this->request->get['sort'] : 'm.sort_order';
        $order = (isset($this->request->get['order']) && $this->request->get['order'] == 'DESC') ? 'DESC' : 'ASC';
        $page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
        $limit = max(1, (int)$this->config->get('config_limit_admin'));

        $url = $this->buildListQuery(array('filter_member_id', 'filter_name', 'filter_category_id', 'filter_city', 'filter_status', 'sort', 'order', 'page'));
        $filter_data = array(
            'filter_member_id' => $filter_member_id,
            'filter_name' => $filter_name,
            'filter_category_id' => $filter_category_id,
            'filter_city' => $filter_city,
            'filter_status' => $filter_status,
            'sort' => $sort,
            'order' => $order,
            'start' => ($page - 1) * $limit,
            'limit' => $limit
        );

        $total = $this->model_extension_probg_team_member->getTotalMembers($filter_data);
        $this->load->model('tool/image');
        $data['members'] = array();

        foreach ($this->model_extension_probg_team_member->getMembers($filter_data) as $result) {
            $thumb = ($result['image'] && is_file(DIR_IMAGE . $result['image']))
                ? $this->model_tool_image->resize($result['image'], 50, 50)
                : $this->model_tool_image->resize('no_image.png', 50, 50);

            $data['members'][] = array(
                'team_member_id' => (int)$result['team_member_id'],
                'thumb' => $thumb,
                'name' => $result['name'],
                'category' => $result['category'],
                'city' => $result['city'],
                'sort_order' => (int)$result['sort_order'],
                'status' => $result['status'] ? $this->language->get('text_enabled') : $this->language->get('text_disabled'),
                'date_modified' => $result['date_modified'],
                'view' => HTTPS_CATALOG . 'index.php?route=extension/probg_team/member&probg_team_category_id=' . (int)$result['team_category_id'] . '&probg_team_member_id=' . (int)$result['team_member_id'],
                'edit' => $this->url->link('extension/probg_team/member/edit', 'user_token=' . $this->session->data['user_token'] . '&team_member_id=' . (int)$result['team_member_id'] . $url, true)
            );
        }

        $this->load->model('extension/probg_team/category');
        $data['categories'] = $this->model_extension_probg_team_category->getCategories(array('sort' => 'cd.name', 'order' => 'ASC', 'start' => 0, 'limit' => 1000));
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/probg_team/member', 'user_token=' . $this->session->data['user_token'] . $url, true))
        );
        $data['add'] = $this->url->link('extension/probg_team/member/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
        $data['delete'] = $this->url->link('extension/probg_team/member/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
        $data['clear'] = $this->url->link('extension/probg_team/member', 'user_token=' . $this->session->data['user_token'], true);
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);
        $data['error_warning'] = isset($this->session->data['error']) ? $this->session->data['error'] : '';
        unset($this->session->data['error']);
        $data['selected'] = isset($this->request->post['selected']) ? (array)$this->request->post['selected'] : array();

        $base_url = 'user_token=' . $this->session->data['user_token']
            . '&filter_member_id=' . urlencode($filter_member_id)
            . '&filter_name=' . urlencode($filter_name)
            . '&filter_category_id=' . urlencode($filter_category_id)
            . '&filter_city=' . urlencode($filter_city)
            . '&filter_status=' . urlencode($filter_status);
        $toggle_order = $order == 'ASC' ? 'DESC' : 'ASC';
        $data['sort_id'] = $this->url->link('extension/probg_team/member', $base_url . '&sort=m.team_member_id&order=' . $toggle_order, true);
        $data['sort_name'] = $this->url->link('extension/probg_team/member', $base_url . '&sort=md.name&order=' . $toggle_order, true);
        $data['sort_category'] = $this->url->link('extension/probg_team/member', $base_url . '&sort=cd.name&order=' . $toggle_order, true);
        $data['sort_city'] = $this->url->link('extension/probg_team/member', $base_url . '&sort=md.city&order=' . $toggle_order, true);
        $data['sort_sort_order'] = $this->url->link('extension/probg_team/member', $base_url . '&sort=m.sort_order&order=' . $toggle_order, true);
        $data['sort_status'] = $this->url->link('extension/probg_team/member', $base_url . '&sort=m.status&order=' . $toggle_order, true);
        $data['sort_date_modified'] = $this->url->link('extension/probg_team/member', $base_url . '&sort=m.date_modified&order=' . $toggle_order, true);

        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link('extension/probg_team/member', $base_url . '&sort=' . $sort . '&order=' . $order . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), $total ? (($page - 1) * $limit) + 1 : 0, min($page * $limit, $total), $total, ceil($total / $limit));
        $data['filter_member_id'] = $filter_member_id;
        $data['filter_name'] = $filter_name;
        $data['filter_category_id'] = $filter_category_id;
        $data['filter_city'] = $filter_city;
        $data['filter_status'] = $filter_status;
        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/probg_team/member_list', $data));
    }

    protected function getForm() {
        $data = $this->load->language('extension/probg_team/member');
        $this->document->addStyle('view/javascript/summernote/summernote.css');
        $this->document->addScript('view/javascript/summernote/summernote.js');
        $this->document->addScript('view/javascript/summernote/opencart.js');

        $member_id = isset($this->request->get['team_member_id']) ? (int)$this->request->get['team_member_id'] : 0;
        $member_info = $member_id ? $this->model_extension_probg_team_member->getMember($member_id) : array();
        $data['text_form'] = $member_id ? $this->language->get('text_edit') : $this->language->get('text_add');
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_name'] = isset($this->error['name']) ? $this->error['name'] : array();
        $data['error_category'] = isset($this->error['category']) ? $this->error['category'] : '';
        $data['error_store'] = isset($this->error['store']) ? $this->error['store'] : '';
        $data['error_seo_url'] = isset($this->error['seo_url']) ? $this->error['seo_url'] : array();
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/probg_team/member', 'user_token=' . $this->session->data['user_token'], true))
        );
        $data['action'] = $member_id
            ? $this->url->link('extension/probg_team/member/edit', 'user_token=' . $this->session->data['user_token'] . '&team_member_id=' . $member_id, true)
            : $this->url->link('extension/probg_team/member/add', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('extension/probg_team/member', 'user_token=' . $this->session->data['user_token'], true);
        $data['member_description'] = isset($this->request->post['member_description']) ? $this->request->post['member_description'] : ($member_id ? $this->model_extension_probg_team_member->getDescriptions($member_id) : array());
        $data['member_seo_url'] = isset($this->request->post['member_seo_url']) ? $this->request->post['member_seo_url'] : ($member_id ? $this->model_extension_probg_team_member->getSeoUrls($member_id) : array());
        $image_rows = isset($this->request->post['member_image']) ? $this->request->post['member_image'] : ($member_id ? $this->model_extension_probg_team_member->getImages($member_id) : array());

        $this->load->model('tool/image');
        $data['member_images'] = array();

        foreach ($image_rows as $row) {
            $image = isset($row['image']) ? $row['image'] : '';
            $data['member_images'][] = array(
                'image' => $image,
                'thumb' => ($image && is_file(DIR_IMAGE . $image)) ? $this->model_tool_image->resize($image, 100, 100) : $this->model_tool_image->resize('no_image.png', 100, 100),
                'sort_order' => isset($row['sort_order']) ? (int)$row['sort_order'] : 0
            );
        }

        $image = isset($this->request->post['image']) ? $this->request->post['image'] : (isset($member_info['image']) ? $member_info['image'] : '');
        $data['image'] = $image;
        $data['thumb'] = ($image && is_file(DIR_IMAGE . $image)) ? $this->model_tool_image->resize($image, 100, 100) : $this->model_tool_image->resize('no_image.png', 100, 100);
        $data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);

        foreach (array('team_category_id' => 0, 'sort_order' => 0, 'status' => 1) as $field => $default) {
            $data[$field] = isset($this->request->post[$field]) ? $this->request->post[$field] : (isset($member_info[$field]) ? $member_info[$field] : $default);
        }

        $this->load->model('extension/probg_team/category');
        $data['categories'] = $this->model_extension_probg_team_category->getCategories(array('sort' => 'cd.name', 'order' => 'ASC', 'start' => 0, 'limit' => 1000));
        $this->load->model('localisation/language');
        $data['languages'] = $this->model_localisation_language->getLanguages();
        $this->load->model('setting/store');
        $data['stores'] = array(array('store_id' => 0, 'name' => $this->config->get('config_name')));

        foreach ($this->model_setting_store->getStores() as $store) {
            $data['stores'][] = array('store_id' => (int)$store['store_id'], 'name' => $store['name']);
        }

        if (isset($this->request->post['member_store'])) {
            $data['member_store'] = array_map('intval', (array)$this->request->post['member_store']);
        } elseif ($member_id) {
            $data['member_store'] = $this->model_extension_probg_team_member->getStores($member_id);
        } else {
            $data['member_store'] = array_column($data['stores'], 'store_id');
        }

        $data['view'] = $member_id ? HTTPS_CATALOG . 'index.php?route=extension/probg_team/member&probg_team_category_id=' . (int)$data['team_category_id'] . '&probg_team_member_id=' . $member_id : '';
        $data['summernote'] = $this->config->get('config_admin_language');
        $data['user_token'] = $this->session->data['user_token'];
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/probg_team/member_form', $data));
    }

    protected function validateForm() {
        if (!$this->user->hasPermission('modify', 'extension/probg_team/member')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        $category_id = isset($this->request->post['team_category_id']) ? (int)$this->request->post['team_category_id'] : 0;
        $this->load->model('extension/probg_team/category');
        $category_info = $category_id ? $this->model_extension_probg_team_category->getCategory($category_id) : array();

        if (!$category_info) {
            $this->error['category'] = $this->language->get('error_category');
        }

        $member_stores = isset($this->request->post['member_store']) ? array_unique(array_map('intval', (array)$this->request->post['member_store'])) : array();
        $this->load->model('setting/store');
        $valid_stores = array(0);

        foreach ($this->model_setting_store->getStores() as $store) {
            $valid_stores[] = (int)$store['store_id'];
        }

        if (!$member_stores || array_diff($member_stores, $valid_stores)) {
            $this->error['store'] = $this->language->get('error_store');
        } elseif ($category_info) {
            $category_stores = $this->model_extension_probg_team_category->getStores($category_id);

            if (array_diff($member_stores, $category_stores)) {
                $this->error['store'] = $this->language->get('error_store_category');
            }
        }

        $descriptions = isset($this->request->post['member_description']) ? $this->request->post['member_description'] : array();
        $this->load->model('localisation/language');

        foreach ($this->model_localisation_language->getLanguages() as $language) {
            $language_id = (int)$language['language_id'];
            $name = isset($descriptions[$language_id]['name']) ? trim($descriptions[$language_id]['name']) : '';

            if ((utf8_strlen($name) < 1) || (utf8_strlen($name) > 255)) {
                $this->error['name'][$language_id] = $this->language->get('error_name');
            }
        }

        $member_id = isset($this->request->get['team_member_id']) ? (int)$this->request->get['team_member_id'] : 0;
        $exclude_query = 'probg_team_member_id=' . $member_id;

        if (!empty($this->request->post['member_seo_url'])) {
            foreach ($this->request->post['member_seo_url'] as $store_id => $language_keywords) {
                foreach ($language_keywords as $language_id => $keyword) {
                    $keyword = trim($keyword);

                    if ($keyword !== '' && !preg_match('/^[\p{L}\p{N}_-]+$/u', $keyword)) {
                        $this->error['seo_url'][$store_id][$language_id] = $this->language->get('error_seo_url_format');
                    } elseif ($keyword !== '' && $this->model_extension_probg_team_member->isSeoKeywordUsed($keyword, $store_id, $language_id, $exclude_query)) {
                        $this->error['seo_url'][$store_id][$language_id] = $this->language->get('error_seo_url');
                    }
                }
            }
        }

        return !$this->error;
    }

    private function loadModels() {
        $this->load->model('extension/module/probg_team');
        $this->model_extension_module_probg_team->upgrade();
        $this->load->model('extension/probg_team/member');
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
