<?php
class ControllerExtensionModuleProbgTeam extends Controller {
    const ADMIN_STRUCTURE_VERSION = '0.9.1';

    public function index() {
        $this->migrateAdminStructure();
        $this->response->redirect($this->url->link('extension/probg_team/setting', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function install() {
        $this->load->model('extension/module/probg_team');
        $this->model_extension_module_probg_team->install();
        $this->grantPermissions();
        $this->migrateAdminStructure(true);
    }

    public function uninstall() {
        $this->load->model('extension/module/probg_team');
        $this->model_extension_module_probg_team->uninstall();

        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_probg_team');

        $this->load->model('setting/module');
        $this->model_setting_module->deleteModulesByCode('probg_team');
        $this->model_setting_module->deleteModulesByCode('probg_team_members');
        $this->model_setting_module->deleteModulesByCode('probg_team_menu');

        $this->db->query("DELETE FROM `" . DB_PREFIX . "extension` WHERE `type` = 'module' AND `code` IN ('probg_team_members', 'probg_team_menu')");
    }

    private function migrateAdminStructure($force = false) {
        $version_query = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'module_probg_team' AND `key` = 'module_probg_team_admin_structure_version' LIMIT 1");
        $version = $version_query->num_rows ? (string)$version_query->row['value'] : '';

        if (!$force && $version === self::ADMIN_STRUCTURE_VERSION) {
            return;
        }

        // v0.9.0 used probg_team for the card Layout module. Keep IDs and Layout positions while moving it to the Blog-style companion-module structure.
        $this->db->query("UPDATE `" . DB_PREFIX . "module` SET `code` = 'probg_team_members' WHERE `code` = 'probg_team'");
        $this->db->query("UPDATE `" . DB_PREFIX . "layout_module` SET `code` = CONCAT('probg_team_members.', SUBSTRING(`code`, 12)) WHERE LEFT(`code`, 11) = 'probg_team.'");

        foreach (array('probg_team_members', 'probg_team_menu') as $code) {
            $extension = $this->db->query("SELECT extension_id FROM `" . DB_PREFIX . "extension` WHERE `type` = 'module' AND `code` = '" . $this->db->escape($code) . "' LIMIT 1");

            if (!$extension->num_rows) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "extension` SET `type` = 'module', `code` = '" . $this->db->escape($code) . "'");
            }
        }

        $this->grantPermissions();

        if ($version_query->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '" . self::ADMIN_STRUCTURE_VERSION . "', serialized = '0' WHERE store_id = '0' AND code = 'module_probg_team' AND `key` = 'module_probg_team_admin_structure_version'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', code = 'module_probg_team', `key` = 'module_probg_team_admin_structure_version', `value` = '" . self::ADMIN_STRUCTURE_VERSION . "', serialized = '0'");
        }
    }

    private function grantPermissions() {
        $this->load->model('user/user_group');
        $group_id = (int)$this->user->getGroupId();
        $routes = array(
            'extension/module/probg_team',
            'extension/module/probg_team_members',
            'extension/module/probg_team_menu',
            'extension/probg_team/setting',
            'extension/probg_team/category',
            'extension/probg_team/member'
        );

        foreach ($routes as $route) {
            $this->model_user_user_group->addPermission($group_id, 'access', $route);
            $this->model_user_user_group->addPermission($group_id, 'modify', $route);
        }
    }
}
