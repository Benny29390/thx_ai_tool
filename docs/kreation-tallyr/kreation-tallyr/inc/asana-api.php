<?php
/**
 * Asana API Integration for Tallyr
 */

if (!defined('ABSPATH')) {
    exit;
}

class Tallyr_Asana_API {

    private $api_token;
    private $base_url = 'https://app.asana.com/api/1.0';

    public function __construct($api_token) {
        $this->api_token = $api_token;
    }

    /**
     * Make API request to Asana
     */
    public function request_public($endpoint) {
        return $this->request($endpoint);
    }

    private function request($endpoint, $method = 'GET', $data = null) {
        $url = $this->base_url . $endpoint;

        $args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        );

        if ($data && $method !== 'GET') {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);

        if ($code === 429) {
            return array('error' => 'rate_limited', 'retry_after' => 60);
        }

        if ($code === 401) {
            return array('error' => 'unauthorized');
        }

        return json_decode($body, true);
    }

    /**
     * Verify token is valid by fetching user info
     */
    public function verify_token() {
        $result = $this->request('/users/me');

        if (isset($result['data']['gid'])) {
            return array(
                'valid' => true,
                'user' => $result['data']
            );
        }

        return array('valid' => false, 'error' => $result);
    }

    /**
     * Get all workspaces for the user
     */
    public function get_workspaces() {
        $result = $this->request('/workspaces');

        if (isset($result['data'])) {
            return $result['data'];
        }

        return array();
    }

    /**
     * Get projects from a workspace (with pagination)
     */
    public function get_projects($workspace_gid) {
        $all_projects = array();
        $seen_gids = array();

        // 1) Projects where user is a member (default endpoint)
        $offset = null;
        do {
            $url = '/workspaces/' . $workspace_gid . '/projects?opt_fields=name,gid&limit=100&archived=false';
            if ($offset) {
                $url .= '&offset=' . $offset;
            }
            $result = $this->request($url);
            if (isset($result['data'])) {
                foreach ($result['data'] as $p) {
                    if (!isset($seen_gids[$p['gid']])) {
                        $all_projects[] = $p;
                        $seen_gids[$p['gid']] = true;
                    }
                }
            }
            $offset = isset($result['next_page']['offset']) ? $result['next_page']['offset'] : null;
        } while ($offset);

        // 2) Also search all projects in workspace via typeahead (catches boards/projects user can access but isn't member of)
        $search_result = $this->request('/workspaces/' . $workspace_gid . '/typeahead?resource_type=project&count=100');
        if (isset($search_result['data'])) {
            foreach ($search_result['data'] as $p) {
                if (!isset($seen_gids[$p['gid']])) {
                    $all_projects[] = array('gid' => $p['gid'], 'name' => $p['name']);
                    $seen_gids[$p['gid']] = true;
                }
            }
        }

        // Sort alphabetically
        usort($all_projects, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $all_projects;
    }

    /**
     * Get all projects from all workspaces
     */
    public function get_all_projects() {
        $workspaces = $this->get_workspaces();
        $all_projects = array();

        foreach ($workspaces as $workspace) {
            $projects = $this->get_projects($workspace['gid']);
            foreach ($projects as $project) {
                $project['workspace_name'] = $workspace['name'];
                $all_projects[] = $project;
            }
        }

        return $all_projects;
    }

    /**
     * Get users in a workspace
     */
    public function get_workspace_users($workspace_gid) {
        $result = $this->request('/workspaces/' . $workspace_gid . '/users?opt_fields=name,gid,email&limit=100');
        if (isset($result['data'])) {
            return $result['data'];
        }
        return array();
    }

    /**
     * Get members of a project
     */
    public function get_project_members($project_gid) {
        $result = $this->request('/projects/' . $project_gid . '?opt_fields=members,members.name');
        if (isset($result['data']['members'])) {
            return $result['data']['members'];
        }
        return array();
    }

    /**
     * Get sections (columns) of a project
     */
    public function get_sections($project_gid) {
        $result = $this->request('/projects/' . $project_gid . '/sections?opt_fields=name,gid');
        if (isset($result['data'])) {
            return $result['data'];
        }
        return array();
    }

    /**
     * Create a task in Asana
     */
    public function create_task($data) {
        $result = $this->request('/tasks', 'POST', array('data' => $data));
        if (isset($result['data'])) {
            return $result['data'];
        }
        return isset($result['error']) ? array('error' => $result['error']) : null;
    }

    /**
     * Add task to a section
     */
    public function add_task_to_section($section_gid, $task_gid) {
        return $this->request('/sections/' . $section_gid . '/addTask', 'POST', array('data' => array('task' => $task_gid)));
    }

    /**
     * Get single task details (including notes/description)
     */
    public function get_task($task_gid) {
        $result = $this->request('/tasks/' . $task_gid . '?opt_fields=name,gid,notes,permalink_url');

        if (isset($result['data'])) {
            return $result['data'];
        }

        return null;
    }

    /**
     * Get tasks from a specific project
     */
    public function get_tasks_from_project($project_gid, $limit = 50) {
        $result = $this->request('/projects/' . $project_gid . '/tasks?opt_fields=name,gid,permalink_url&limit=' . $limit);

        if (isset($result['data'])) {
            return $result['data'];
        }

        return array();
    }

    /**
     * Search tasks by text using typeahead (works for all accounts, not just Premium)
     */
    public function search_tasks($workspace_gid, $search_text, $project_gid = null, $limit = 20) {
        // If we have a project, search within that project
        if ($project_gid && !empty($search_text)) {
            // Get all tasks from project and filter client-side
            $tasks = $this->get_tasks_from_project($project_gid, 100);
            $filtered = array();
            $search_lower = strtolower($search_text);

            foreach ($tasks as $task) {
                if (stripos($task['name'], $search_text) !== false) {
                    $filtered[] = $task;
                    if (count($filtered) >= $limit) {
                        break;
                    }
                }
            }
            return $filtered;
        }

        // Use typeahead endpoint for workspace-wide search (works for all accounts)
        $endpoint = '/workspaces/' . $workspace_gid . '/typeahead?resource_type=task&query=' . urlencode($search_text) . '&count=' . $limit;

        $result = $this->request($endpoint);

        if (isset($result['data'])) {
            // Typeahead returns limited fields, fetch permalink_url for each task
            $tasks_with_urls = array();
            foreach ($result['data'] as $task) {
                $tasks_with_urls[] = array(
                    'gid' => $task['gid'],
                    'name' => $task['name'],
                    'permalink_url' => 'https://app.asana.com/0/0/' . $task['gid']
                );
            }
            return $tasks_with_urls;
        }

        return array();
    }

    /**
     * Get tasks from workspace (recent incomplete tasks assigned to current user)
     */
    public function get_tasks_from_workspace($workspace_gid, $limit = 50) {
        // Get user GID first
        $user_result = $this->request('/users/me');
        if (!isset($user_result['data']['gid'])) {
            return array();
        }
        $user_gid = $user_result['data']['gid'];

        // Get tasks assigned to user
        $endpoint = '/tasks?workspace=' . $workspace_gid . '&assignee=' . $user_gid . '&opt_fields=name,gid,permalink_url&limit=' . $limit;

        $result = $this->request($endpoint);

        if (isset($result['data'])) {
            return $result['data'];
        }

        return array();
    }
}
