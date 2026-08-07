<?php
class ControllerExtensionFeedProbgTeamSitemap extends Controller {
    public function index() {
        if (!$this->config->get('module_probg_team_status') || !$this->config->get('module_probg_team_sitemap_status')) {
            $this->request->get['route'] = 'error/not_found';
            return $this->load->controller('error/not_found');
        }

        $this->load->model('extension/probg_team/team');
        $section = $this->model_extension_probg_team_team->getSectionDescription();

        if (!$section) {
            $this->request->get['route'] = 'error/not_found';
            return $this->load->controller('error/not_found');
        }

        $output = '<?xml version="1.0" encoding="UTF-8"?>';
        $output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        $output .= $this->buildUrl($this->url->link('extension/probg_team/team'), '', 'weekly', '0.8');

        foreach ($this->model_extension_probg_team_team->getSitemapCategories() as $category) {
            $output .= $this->buildUrl(
                $this->url->link('extension/probg_team/category', 'probg_team_category_id=' . (int)$category['team_category_id']),
                $category['date_modified'],
                'weekly',
                '0.7'
            );
        }

        foreach ($this->model_extension_probg_team_team->getSitemapMembers() as $member) {
            $output .= $this->buildUrl(
                $this->url->link('extension/probg_team/member', 'probg_team_category_id=' . (int)$member['team_category_id'] . '&probg_team_member_id=' . (int)$member['team_member_id']),
                $member['date_modified'],
                'weekly',
                '0.6'
            );
        }

        $output .= '</urlset>';

        $this->response->addHeader('Content-Type: application/xml; charset=UTF-8');
        $this->response->setOutput($output);
    }

    private function buildUrl($url, $date_modified = '', $changefreq = 'weekly', $priority = '0.5') {
        $output = '<url>';
        $output .= '<loc>' . $this->xml(htmlspecialchars_decode($url, ENT_QUOTES)) . '</loc>';
        $output .= '<changefreq>' . $changefreq . '</changefreq>';

        if ($date_modified && strtotime($date_modified)) {
            $output .= '<lastmod>' . date('c', strtotime($date_modified)) . '</lastmod>';
        }

        $output .= '<priority>' . $priority . '</priority>';
        $output .= '</url>';

        return $output;
    }

    private function xml($value) {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
