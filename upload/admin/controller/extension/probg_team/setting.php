<?php
class ControllerExtensionProbgTeamSetting extends Controller {
    public function index() {
        $this->response->redirect($this->url->link('extension/module/probg_team', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function repair() {
        $this->response->redirect($this->url->link('extension/module/probg_team/repair', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function clearCache() {
        $this->response->redirect($this->url->link('extension/module/probg_team/clearCache', 'user_token=' . $this->session->data['user_token'], true));
    }
}
