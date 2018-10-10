<?php

elgg_register_event_handler('init', 'system', 'events_collector_init');

function events_collector_init() {

    // Plugin settings
    $period = elgg_get_plugin_setting('period', 'events_collector');
    $delete = elgg_get_plugin_setting('delete', 'events_collector');
    switch ($period) {
        case 'weekly':
        case 'monthly' :
        case 'yearly' :
            break;
        default:
            $period = 'monthly';
    }

    // Register cron hook for archival of logs
    elgg_register_plugin_hook_handler('cron', $period, 'events_collector_archive_cron');

    if ($delete != 'never') {
        // Register cron hook for deletion of selected archived logs
        elgg_register_plugin_hook_handler('cron', $delete, 'events_collector_delete_cron');
    }

    // register to receive requests that start with 'events_collector'
    elgg_register_page_handler('events_collector', 'events_collector_page_handler');

    // add a menu item to primary site navigation
    $user_guid = elgg_get_logged_in_user_guid();
    $user = elgg_get_logged_in_user_entity();

    if ($user instanceof ElggUser){
        if ($user->isAdmin()){
            $item = new ElggMenuItem('eventsbrowser', elgg_echo('events_collector:events_collector'), "events_collector/eventsbrowser?user_guid={$user_guid}");
            elgg_register_menu_item('site', $item);
            // El evento system, pagesetup se ejecutá antes de que se cargue una página
            elgg_register_event_handler("pagesetup", "system", "group_pagesetup", 549);
        }
    }

    // Extend system CSS with our own styles, which are defined in the events_collector/css view
    elgg_extend_view('css/elgg','events_collector/css');

    // We register the library with Elgg in the plugin initialization function and tell Elgg to load it on every page
    $lib = elgg_get_plugins_path() . 'events_collector/lib/events_collector.php';
    elgg_register_library('events_collector', $lib);
    elgg_load_library('events_collector');

    elgg_register_event_handler('login:before', 'user', 'events_collector_event_login_user_save');

    elgg_register_event_handler('create', 'object', 'events_collector_event_create_object_save');
    elgg_register_event_handler('update', 'object', 'events_collector_event_update_object_save');
    elgg_register_event_handler('delete', 'object', 'events_collector_event_delete_object_save');

    elgg_register_event_handler('create', 'annotation', 'events_collector_event_create_annotation_save');
    elgg_register_event_handler('update', 'annotation', 'events_collector_event_update_annotation_save');
    elgg_register_event_handler('delete', 'annotations', 'events_collector_event_delete_annotations_save');

    elgg_register_event_handler('create', 'friend', 'events_collector_event_follow_save');
    elgg_register_event_handler('delete', 'friend', 'events_collector_event_unfollow_save');


    elgg_register_plugin_hook_handler('route', 'blog', 'events_collector_route_handler');
    elgg_register_plugin_hook_handler('route', 'file', 'events_collector_route_handler');
    elgg_register_plugin_hook_handler('route', 'bookmarks', 'events_collector_route_handler');
    elgg_register_plugin_hook_handler('route', 'pages', 'events_collector_route_handler');
    elgg_register_plugin_hook_handler('route', 'discussion', 'events_collector_route_handler');
    elgg_register_plugin_hook_handler('route', 'questions', 'events_collector_route_handler');
    elgg_register_plugin_hook_handler('route', 'task', 'events_collector_route_handler');

    elgg_register_plugin_hook_handler('register', 'menu:owner_block', 'events_collector_owner_block_menu');


    //Creamos tabla (si no existe) en la base de datos de elgg
    $dbprefix = elgg_get_config('dbprefix');
    $sql = "CREATE TABLE IF NOT EXISTS `{$dbprefix}events_log` (";
    $sql .= "`id` int(11) NOT NULL AUTO_INCREMENT,";
    $sql .= "`actor_guid` int(11) NOT NULL,";
    $sql .= "`action_type` varchar(15) NOT NULL,";
    $sql .= "`resource_type` varchar(20) NOT NULL,";
    $sql .= "`resource_guid` int(11) NOT NULL,";
    $sql .= "`tags` text NOT NULL,";
    $sql .= "`categories` text NOT NULL,";
    $sql .= "`owner_guid` int(11) NOT NULL,";
    $sql .= "`container_guid` int(11) NOT NULL,";
    $sql .= "`time_created` int(11) NOT NULL,";
    $sql .= "PRIMARY KEY (`id`)";
    $sql .= ") ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;";
    update_data($sql);
}

/**
 * Trigger the log rotation.
 */
function events_collector_archive_cron($hook, $entity_type, $returnvalue, $params) {
    $resulttext = elgg_echo("events_collector:events_collectord");

    $day = 86400;

    $offset = 0;
    $period = elgg_get_plugin_setting('period', 'events_collector');
    switch ($period) {
        case 'weekly':
            $offset = $day * 7;
            break;
        case 'yearly':
            $offset = $day * 365;
            break;
        case 'monthly':
        default:
            // assume 28 days even if a month is longer. Won't cause data loss.
            $offset = $day * 28;
    }

    if (!events_collector_archive_log($offset)) {
        $resulttext = elgg_echo("events_collector:lognotrotated");
    }

    return $returnvalue . $resulttext;
}

/**
 * Trigger the log archiving.
 */
function events_collector_archive_log($offset = 0) {
 
    $offset = (int)$offset;
    $now = time(); // Take a snapshot of now

    $ts = $now - $offset;

    // create table
    $dbprefix = elgg_get_config('dbprefix');
    $query = "CREATE TABLE {$dbprefix}events_log_$now as
        SELECT * from {$dbprefix}events_log WHERE time_created<$ts";

    if (!update_data($query)) {
        return false;
    }

    // delete
    // Don't delete on time since we are running in a concurrent environment
    if (delete_data("DELETE from {$dbprefix}events_log WHERE time_created<$ts") === false) {
        return false;
    }

    return true;
}

/**
 * Trigger the log deletion.
 */
function events_collector_delete_cron($hook, $entity_type, $returnvalue, $params) {
    $resulttext = elgg_echo("events_collector:logdeleted");

    $day = 86400;

    $offset = 0;
    $period = elgg_get_plugin_setting('delete', 'events_collector');
    switch ($period) {
        case 'weekly':
            $offset = $day * 7;
            break;
        case 'yearly':
            $offset = $day * 365;
            break;
        case 'monthly':
        default:
            // assume 28 days even if a month is longer. Won't cause data loss.
            $offset = $day * 28;
    }

    if (!events_collector_delete_log($offset)) {
        $resulttext = elgg_echo("events_collector:lognotdeleted");
    }

    return $returnvalue . $resulttext;
}

/**
 * This function deletes logs that are older than specified.
 *
 * @param int $time_of_delete An offset in seconds from now to delete log tables
 * @return bool Were any log tables deleted
 */
function events_collector_delete_log($time_of_delete) {

    $cutoff = time() - (int)$time_of_delete;

    $deleted_tables = false;
    $dbprefix = elgg_get_config('dbprefix');
    $results = get_data("SHOW TABLES like '{$dbprefix}events_log_%'");
    if ($results) {
        foreach ($results as $result) {
            $data = (array)$result;
            $table_name = array_shift($data);
            // extract log table rotation time
            $log_time = str_replace("{$dbprefix}events_log_", '', $table_name);
            if ($log_time < $cutoff) {
                if (delete_data("DROP TABLE $table_name") !== false) {
                    // delete_data returns 0 when dropping a table (false for failure)
                    $deleted_tables = true;
                } else {
                    elgg_log("Failed to delete the log table $table_name", 'ERROR');
                }
            }
        }
    }

    return $deleted_tables;
}


function events_collector_page_handler($page, $identifier) {

    // select page based on first URL segment after /events_collector/
    switch ($page[0]) {
        case 'eventsbrowser':
            //require "$base_path/eventsbrowser.php";
            //Bloquea a los usuarios que no están registrados
            gatekeeper();
            $vars = array('page' => $page);
            $view = 'events_collector/' . implode('/', $page);
            $title = elgg_echo("events_collector:{$page[0]}");

            //En el siguiente if obtiene el $content (yendo a la vista events_collector/eventsbrowser)
            if ($page[0] == 'components' || !($content = elgg_view($view, $vars))) {
                $title = elgg_echo('admin:unknown_section');
                $content = elgg_echo('admin:unknown_section');
            }

            $body = elgg_view('page/layout/eventsbrowser', array('content' => $content, 'title' => $title, 'filter' => '',));
            echo elgg_view_page($title, $body);
            break;

        case 'members':
            $guid = $page[1];
            elgg_set_page_owner_guid($guid);

            $group = get_entity($guid);
            if (!$group || !elgg_instanceof($group, 'group')) {
                forward();
            }

            $loggedin_user = elgg_get_logged_in_user_entity();
            group_gatekeeper();
            if (events_collector_is_logged_in_group_admin($guid) || $loggedin_user->isAdmin()) {
                $page_title = elgg_echo('events_collector:groups:members:title' , array($group->name));
                $wwwroot = elgg_get_config('wwwroot');
                $content .= order_last_activiy_links($wwwroot);


                elgg_push_breadcrumb($group->name, $group->getURL());
                elgg_push_breadcrumb(elgg_echo('groups:members'));

                $order_by = get_input('order_by','time_created');
                $criteria = get_input('criteria','asc');

                $dbprefix = elgg_get_config('dbprefix');
                $options = array(
                    'relationship' => 'member',
                    'relationship_guid' => $group->guid,
                    'inverse_relationship' => true,
                    'type' => 'user',
                    'limit' => 1000,
                    'joins' => array("JOIN {$dbprefix}users_entity u ON e.guid=u.guid"),
                );
                $users = elgg_get_entities_from_relationship($options);
                
                    $rows_max_time = array();
                    foreach ($users as $user){
                        $query = "SELECT * FROM {$dbprefix}events_log WHERE actor_guid=$user->guid AND container_guid=$guid ORDER BY time_created DESC LIMIT 1";
                        $row_max_data = get_data($query);
                        if ($row_max_data[0] != NULL)
                            array_push($rows_max_time, $row_max_data[0]);
                    }
                if ($order_by == 'time_created'){
                    if ($criteria == 'asc'){
                        usort($rows_max_time, function($a, $b) {
                            return $b->time_created - $a->time_created;
                        }); 
                    }    
                    else{
                        usort($rows_max_time, function($a, $b) {
                            return $a->time_created - $b->time_created;
                        }); 
                    }  
                    $users_max_time = array();
                    foreach ($rows_max_time as $row_max_time){
                        array_push($users_max_time, get_user($row_max_time->actor_guid));
                    }   
                    $content .= elgg_view('eventsbrowser/recent_activity', array('entities' => $users_max_time, 'vars' => $options, 'size' => 'small', 'container_guid' => $guid));
                }      
                if ($order_by == 'name'){
                    if ($criteria == 'desc'){
                        usort($rows_max_time, function($a, $b) {
                            $aa = get_user($a->actor_guid);
                            $bb = get_user($b->actor_guid);
                            return $aa->name < $bb->name;
                        }); 
                    }
                    if ($criteria == 'asc'){
                        usort($rows_max_time, function($a, $b) {
                            $aa = get_user($a->actor_guid);
                            $bb = get_user($b->actor_guid);
                            return $aa->name >= $bb->name;
                        }); 
                    }
                    $users_m = array();
                    foreach ($rows_max_time as $row_m){
                        array_push($users_m, get_user($row_m->actor_guid));
                    }   
                    $content .= elgg_view('eventsbrowser/recent_activity', array('entities' => $users_m, 'vars' => $options, 'size' => 'small', 'container_guid' => $guid));
                }

                $params = array(
                    'content' => $content,
                    'title' => $page_title,
                    'filter' => '',
                );
                $body = elgg_view_layout('content', $params);
                echo elgg_view_page($title, $body);
            } else {
                $_SESSION['last_forward_from'] = current_page_url(); // Si intenta ver otros eventos, se le mostrará un mensaje de error y lo enviaremos a la página anterior
                register_error(elgg_echo('events_collector:userrequired'));
                forward(REFERER);
            }
            break;

        case 'export':
            if (count($page) > 1) {
                if (strcmp($page[1], 'csv') == 0) {
                    $limit = get_input('limit', 20);
                    $offset = get_input('offset');
                    $limit = 0;
                    //Cogiendo parámetros
                    $user_guid = get_input('user_guid', null);
                    $timelower = get_input('timelower');
                    if ($timelower) {
                        $timelower = strtotime($timelower);
                    }
                    $timeupper = get_input('timeupper');
                    if ($timeupper) {
                        $timeupper = strtotime($timeupper);
                    }
                    $container_guid = get_input('container_guid', null);
                    $action_type = get_input('action_type');
                    $resource_type = get_input('resource_type');

                    events_collector_admin_gatekeeper($container_guid,$user_guid);
                    $query = "SHOW COLUMNS FROM elgg_events_log";
                    $result = get_data($query);
                    $columns = array();
                    $i = 0;
                    foreach ($result as $column) {
                        $salida_cvs .= $column->Field . ",";
                        $columns[$i] = $column->Field;
                        $i++;
                    }
                    $salida_cvs .= "\n";
                    $values = get_events($user_guid, "", "", "", "", $limit, $offset, false, $timeupper, $timelower, 0, $action_type, $resource_type, $container_guid);
                    foreach ($values as $entry) {
                        //For del tamaño del array
                        foreach ($columns as $column_name) {
                            if ($column_name == 'tags' || $column_name == 'categories') {
                                $salida_cvs .= "\"" . $entry->$column_name . "\",";
                            } else {
                                $salida_cvs .= $entry->$column_name . ",";
                            }
                        }
                        $salida_cvs .= "\n";
                    }
                    header("Content-type: application/vnd.ms-excel");
                    header("Content-disposition: csv" . date("Y-m-d") . ".csv");
                    header("Content-disposition: filename=export_events.csv");
                    print $salida_cvs;
                    exit;
                }
            }
            break;
                
        default:
            echo "request for $identifier $page[0]";
            break;
    }
    // return true to let Elgg know that a page was sent to browser
    return true;
}

/**
 * Add a menu item to an ownerblock
 */
function events_collector_owner_block_menu($hook, $type, $return, $params) {
    $user = elgg_get_logged_in_user_entity();
    if (elgg_instanceof($params['entity'], 'group')) {
        $page_owner = elgg_get_page_owner_entity();
        $group_guid = $page_owner->getGUID(); //GUID del grupo
        if (events_collector_is_logged_in_group_admin($group_guid) || $user->isAdmin()) {
            $url = "events_collector/eventsbrowser?container_guid={$group_guid}";
            $item = new ElggMenuItem('events_collector_group_events', elgg_echo('events_collector:group'), $url);
            $return[] = $item;
            $url2 = "events_collector/members/{$group_guid}";
            $item2 = new ElggMenuItem('events_collector_group_members', elgg_echo('events_collector:members:recent_activity'), $url2);
            $return[] = $item2;
        }
    } else {
        if (elgg_instanceof($params['entity'], 'user') && $user->isAdmin()) {
            $user_guid = elgg_get_logged_in_user_guid();
            $url = "events_collector/eventsbrowser?user_guid={$user_guid}";
            $item = new ElggMenuItem('events_collector_user_events', elgg_echo('events_collector:user_button'), $url);
            $return[] = $item;
        }
    }
    return $return;
}

function events_collector_event_login_user_save($event, $type, $object) {
    // Viene aqui cuando entramos en la página (cuando nos logeamos)
    static $log_cache;
    static $cache_size = 0;

    if ($object instanceof Loggable) {
        // reset cache if it has grown too large
        if (!is_array($log_cache) || $cache_size > 500) {
            $log_cache = array();
            $cache_size = 0;
        }
        $actor_guid = elgg_get_logged_in_user_guid();
        $resource_type = $object->getSubtype();
        $action_type = "LOGGED";
        $resource_guid = (int) $object->getSystemLogID();
        if (isset($object->owner_guid)) {
            $owner_guid = $object->owner_guid;
        } else {
            $owner_guid = 0;
        }
        if (empty($owner_guid)) {
            $owner_guid = $actor_guid;
        }
        if (isset($object->container_guid)) {
            $container_guid = $object->container_guid;
        } else {
            $container_guid = 0;
        }
        if (empty($container_guid)) {
            $container_guid = $actor_guid;
        }
        $time = time();
         // Create log if we haven't already created it
        if (!isset($log_cache[$time][$resource_guid][$event])) {
            $dbprefix = elgg_get_config('dbprefix');
            $query = "INSERT DELAYED into {$dbprefix}events_log
				(actor_guid, action_type, resource_type, resource_guid, owner_guid, container_guid, time_created) "
                    . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$owner_guid','$container_guid','$time')";

            insert_data($query);
            $log_cache[$time][$resource_guid][$event] = true;
            $cache_size += 1;
        }
    }
}

function events_collector_event_create_object_save($event, $type, $object) {
    // Viene aqui cuando creamos un blog, una discusión, un enlace, subimos un archivo, una pregunta, una tarea
    static $log_cache;
    static $cache_size = 0;
    elgg_log("FUNCION CREATE",'ERROR');
    if ($object instanceof Loggable) {
        // reset cache if it has grown too large
        if (!is_array($log_cache) || $cache_size > 500) {
            $log_cache = array();
            $cache_size = 0;
        }
        $actor_guid = elgg_get_logged_in_user_guid();
        $resource_type = $object->getSubtype();

        if ($resource_type == 'form_question' || $resource_type == 'questionsbank' || $resource_type == 'test_question' || $resource_type == 'test_answer_draft' || $resource_type == 'task_response_file'){
            return;
        }

        if ($resource_type == 'answer' || $resource_type == 'discussion_reply' || $resource_type == 'contest_answer' || $resource_type == 'form_answer' || $resource_type == 'test_answer' || $resource_type == 'poll_answer') {
            $action_type = "RESPONSED";
        } else if (strcmp($resource_type, 'task_answer') == 0) {
            $taskpost = get_input('taskpost');
            $task = get_entity($taskpost);
            $action_type = "RESPONSED";
            $object = $task;
        } else if (strcmp($resource_type, 'file') == 0){
            $action_type = "UPLOADED"; 
        } else if (strcmp($resource_type, 'comment') == 0){
            $action_type = "COMMENTED";
        }else{
            $action_type = "CREATED";
        }

        if($resource_type=='comment' || $resource_type == 'discussion_reply' || $resource_type == 'answer' || $resource_type == 'contest_answer' || $resource_type == 'form_answer' || $resource_type == 'form_answer' || $resource_type == 'poll_answer'){
            switch($resource_type){
                case 'discussion_reply':
                    $resource_type = 'discussion';
                    break;
                case 'task_answer':
                    $resource_type = 'task';
                    break;
                case 'answer':
                    $resource_type = 'question';
                    break;
                case 'contest_answer':
                    $resource_type = 'contest';
                    break;
                case 'form_answer':
                    $resource_type = 'form';
                    break;
                case 'test_answer':
                    $resource_type = 'test';
                    break;
                case 'poll_answer':
                    $resource_type = 'poll';
                    break;
                default:
                    break;
            }
            $resource_guid = (int) $object->container_guid;
            if (isset($object->owner_guid)) {
                $owner_guid = $object->owner_guid;
            } else {
                $owner_guid = 0;
            }
            if (isset($object->container_guid)) {
                $father = get_entity($object->container_guid);
                $resource_type = $father->getSubtype();
                $container_guid = $father->container_guid;
            } else {
                $container_guid = 0;
            }

        }else{
            $resource_guid = (int) $object->getSystemLogID();
            if (isset($object->owner_guid)) {
                $owner_guid = $object->owner_guid;
            } else {
                $owner_guid = 0;
            }
            if (isset($object->container_guid)) {
                $container_guid = $object->container_guid;
            } else {
                $container_guid = 0;
            }
        }

       

        $time_db = time();
      
        $array_tags = $object->tags;
        if (!empty($array_tags)) {
            if (count($array_tags) == 1) {
                $tags = (string) $array_tags;
            } else {
                $tags = implode(",", $array_tags);
            }
        } else {
            $tags = false;
        }
        if ($object instanceof ElggEntity) {
            $marker = get_input('universal_category_marker');
            if ($marker == 'on') {
                $array_categories = get_input('universal_categories_list');
                $sd = get_input('categories');
                if (!empty($array_categories)) {
                    if (count($array_categories) == 1) {
                        $categories = (string) $array_categories[0];
                    } else {
                        $categories = implode(",", $array_categories);
                    }
                } else {
                    $categories = false;
                }
            } else {
                $array_hype_categories = get_input('categories');
                if (!empty($array_hype_categories)) {
                    foreach ($array_hype_categories as $hype_category_entity_guid) {
                        $hype_category_entity = get_entity($hype_category_entity_guid);
                        if (empty($categories)) {
                            $categories = $hype_category_entity->title;
                        } else {
                            $categories .= ',' . $hype_category_entity->title;
                        }
                    }
                } else {
                    $categories = false;
                }
            }
        }
        // Create log if we haven't already created it
        if (!isset($log_cache[$time][$resource_guid][$event])) {
            $dbprefix = elgg_get_config('dbprefix');

            $query = "INSERT DELAYED into {$dbprefix}events_log "
                    . "(actor_guid, action_type, resource_type, resource_guid, tags, categories, owner_guid, container_guid, time_created) "
                    . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$tags','$categories','$owner_guid','$container_guid','$time_db')";

            insert_data($query);
            $log_cache[$time][$resource_guid][$event] = true;
            $cache_size += 1;
        }
    }
}

function events_collector_event_update_object_save($event, $type, $object) {
    // Viene aqui cuando actualizamos un blog, una discusión, un enlace, subimos un archivo, una pregunta, una tarea
    static $log_cache;
    static $cache_size = 0;

    if ($object instanceof Loggable) {
        // reset cache if it has grown too large
        if (!is_array($log_cache) || $cache_size > 500) {
            $log_cache = array();
            $cache_size = 0;
        }
        $actor_guid = elgg_get_logged_in_user_guid();
        $resource_type = $object->getSubtype();
        $action_type = "UPDATED";

        if($resource_type=='comment'){

            $resource_guid = (int) $object->container_guid;
            if (isset($object->owner_guid)) {
                $owner_guid = $object->owner_guid;
            } else {
                $owner_guid = 0;
            }
            if (isset($object->container_guid)) {
                $father = get_entity($object->container_guid);
                $container_guid = $father->container_guid;
            } else {
                $container_guid = 0;
            }

        }else{
            $resource_guid = (int) $object->getSystemLogID();
            if (isset($object->owner_guid)) {
                $owner_guid = $object->owner_guid;
            } else {
                $owner_guid = 0;
            }
            if (isset($object->container_guid)) {
                $container_guid = $object->container_guid;
            } else {
                $container_guid = 0;
            }
        }
        $time = time();

        if (strcmp($resource_type, 'answer') != 0) {//Si no es una answer (respuesta a una pregunta) actuamos normal
            $array_tags = $object->tags;
            if (!empty($array_tags)) {
                if (count($array_tags) == 1) {
                    $tags = (string) $array_tags;
                } else {
                    $tags = implode(",", $array_tags);
                }
            } else {
                $tags = false;
            }
            if ($object instanceof ElggEntity) {
                $marker = get_input('universal_category_marker');
                if ($marker == 'on') {
                    $array_categories = get_input('universal_categories_list');
                    if (!empty($array_categories)) {
                        if (count($array_categories) == 1) {
                            $categories = (string) $array_categories[0];
                        } else {
                            $categories = implode(",", $array_categories);
                        }
                    } else {
                        $categories = false;
                    }
                } else {
                    $array_hype_categories = events_collector_get_entity_categories($resource_guid, array(), true);
                    if (!empty($array_hype_categories)) {
                        foreach ($array_hype_categories as $hype_category_entity_guid) {
                            $hype_category_entity = get_entity($hype_category_entity_guid);
                            if (empty($categories)) {
                                $categories = $hype_category_entity->title;
                            } else {
                                $categories .= ',' . $hype_category_entity->title;
                            }
                        }
                    } else {
                        $categories = false;
                    }
                }
            }
            // Create log if we haven't already created it
            if (!isset($log_cache[$time][$resource_guid][$event])) {
                $dbprefix = elgg_get_config('dbprefix');
                $query = "INSERT DELAYED into {$dbprefix}events_log "
                        . "(actor_guid, action_type, resource_type, resource_guid, tags, categories, owner_guid, container_guid, time_created) "
                        . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$tags','$categories','$owner_guid','$container_guid','$time')";

                insert_data($query);
                $log_cache[$time][$resource_guid][$event] = true;
                $cache_size += 1;
            }
        } else {
            $question = get_entity($container_guid);
            if ($question) {
                $question_container_guid = $question->container_guid;
                $group = get_entity($question_container_guid);
                if (!empty($group) && elgg_instanceof($group, "group")) {
                    $array_tags = $question->tags;
                    if (!empty($array_tags)) {
                        if (count($array_tags) == 1) {
                            $tags = (string) $array_tags;
                        } else {
                            $tags = implode(",", $array_tags);
                        }
                    } else {
                        $tags = false;
                    }
                    if ($question instanceof ElggEntity) {
                        $marker = get_input('universal_category_marker');
                        if ($marker == 'on') {
                            $array_categories = get_input('universal_categories_list');
                            if (!empty($array_categories)) {
                                if (count($array_categories) == 1) {
                                    $categories = (string) $array_categories[0];
                                } else {
                                    $categories = implode(",", $array_categories);
                                }
                            } else {
                                $categories = false;
                            }
                        } else {
                            $array_hype_categories = events_collector_get_entity_categories($resource_guid, array(), true);
                            if (!empty($array_hype_categories)) {
                                foreach ($array_hype_categories as $hype_category_entity_guid) {
                                    $hype_category_entity = get_entity($hype_category_entity_guid);
                                    if (empty($categories)) {
                                        $categories = $hype_category_entity->title;
                                    } else {
                                        $categories .= ',' . $hype_category_entity->title;
                                    }
                                }
                            } else {
                                $categories = false;
                            }
                        }
                    }
                    $container_guid = $question_container_guid;
                    $action_type = "RESPONSED";
                    $question_resource_guid = (int) $question->getSystemLogID();
                    if (isset($question->owner_guid)) {
                        $owner_guid = $question->owner_guid;
                    } else {
                        $owner_guid = 0;
                    }
                    // Create log if we haven't already created it
                    if (!isset($log_cache[$time][$resource_guid][$event])) {
                        $dbprefix = elgg_get_config('dbprefix');
                        $query = "UPDATE {$dbprefix}events_log SET container_guid=$container_guid, /*resource_guid=$question_resource_guid,*/"
                                . " owner_guid=$owner_guid, tags='$tags', categories='$categories' "
                                . "WHERE actor_guid=$actor_guid AND action_type='$action_type' AND resource_type='$resource_type'"
                                . "AND resource_guid=$resource_guid";
                        update_data($query);
                        $log_cache[$time][$resource_guid][$event] = true;
                        $cache_size += 1;
                    }
                }
            }
        }
    }
}

function events_collector_event_delete_object_save($event, $type, $object) {
    // Viene aqui cuando borramos un blog, una discusión, un enlace, subimos un archivo, una pregunta, una tarea
    static $log_cache;
    static $cache_size = 0;

    if ($object instanceof Loggable) {

        // reset cache if it has grown too large
        if (!is_array($log_cache) || $cache_size > 500) {
            $log_cache = array();
            $cache_size = 0;
        }
        $actor_guid = elgg_get_logged_in_user_guid();
        $resource_type = $object->getSubtype();
        if (strcmp($resource_type, 'task_answer') == 0) {
            $taskpost = get_input('taskpost');
            $task = get_entity($taskpost);
            $object = $task;
        }
        $action_type = "REMOVED";

        if($resource_type=='comment'){

            $resource_guid = (int) $object->container_guid;
            if (isset($object->owner_guid)) {
                $owner_guid = $object->owner_guid;
            } else {
                $owner_guid = 0;
            }
            if (isset($object->container_guid)) {
                $father = get_entity($object->container_guid);
                $container_guid = $father->container_guid;
            } else {
                $container_guid = 0;
            }

        }elseif ($object instanceof ElggObject){
            $resource_guid = (int) $object->getSystemLogID();
            if (isset($object->owner_guid)) {
                $owner_guid = $object->owner_guid;
            } else {
                $owner_guid = 0;
            }
            if (isset($object->container_guid)) {
                $container_guid = $object->container_guid;
            } else {
                $container_guid = 0;
            }
        }
        $time = time();

        $array_tags = $object->tags;
        if (!empty($array_tags)) {
            if (count($array_tags) == 1) {
                $tags = (string) $array_tags;
            } else {
                $tags = implode(",", $array_tags);
            }
        } else {
            $tags = false;
        }

        $array_categories = $object->universal_categories;
        if (!empty($array_categories)) {
            if (count($array_categories) == 1) {
                $categories = (string) $array_categories;
            } else {
                $categories = implode(",", $array_categories);
            }
        } else {
            $array_hype_categories = events_collector_get_entity_categories($resource_guid, array(), true);
            if (!empty($array_hype_categories)) {
                foreach ($array_hype_categories as $hype_category_entity_guid) {
                    $hype_category_entity = get_entity($hype_category_entity_guid);
                    if (empty($categories)) {
                        $categories = $hype_category_entity->title;
                    } else {
                        $categories .= ',' . $hype_category_entity->title;
                    }
                }
            } else {
                $categories = false;
            }
        }

        if (strcmp($resource_type, 'answer') == 0) {
            $question = get_entity($container_guid);
            if ($question) {
                $question_container_guid = $question->container_guid;
                $group = get_entity($question_container_guid);
                if (!empty($group) && elgg_instanceof($group, "group")) {
                    $array_tags = $question->tags;
                    if (!empty($array_tags)) {
                        if (count($array_tags) == 1) {
                            $tags = (string) $array_tags;
                        } else {
                            $tags = implode(",", $array_tags);
                        }
                    } else {
                        $tags = false;
                    }
                    if ($question instanceof ElggEntity) {
                        $marker = get_input('universal_category_marker');
                        if ($marker == 'on') {
                            $array_categories = get_input('universal_categories_list');
                            if (!empty($array_categories)) {
                                if (count($array_categories) == 1) {
                                    $categories = (string) $array_categories[0];
                                } else {
                                    $categories = implode(",", $array_categories);
                                }
                            } else {
                                $categories = false;
                            }
                        } else {
                            $array_hype_categories = get_input('categories');
                            if (!empty($array_hype_categories)) {
                                foreach ($array_hype_categories as $hype_category_entity_guid) {
                                    $hype_category_entity = get_entity($hype_category_entity_guid);
                                    if (empty($categories)) {
                                        $categories = $hype_category_entity->title;
                                    } else {
                                        $categories .= ',' . $hype_category_entity->title;
                                    }
                                }
                            } else {
                                $categories = false;
                            }
                        }
                    }
                    $container_guid = $question_container_guid;
                    $resource_guid = (int) $question->getSystemLogID();
                    if (isset($question->owner_guid)) {
                        $owner_guid = $question->owner_guid;
                    } else {
                        $owner_guid = 0;
                    }
                }
            }
        }
        // Create log if we haven't already created it
        if (!isset($log_cache[$time][$resource_guid][$event])) {
            $dbprefix = elgg_get_config('dbprefix');
            $query = "INSERT DELAYED into {$dbprefix}events_log "
                    . "(actor_guid, action_type, resource_type, resource_guid, tags, categories, owner_guid, container_guid, time_created) "
                    . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$tags','$categories','$owner_guid','$container_guid','$time')";
            insert_data($query);
            $log_cache[$time][$resource_guid][$event] = true;
            $cache_size += 1;
        }
    }
}

function events_collector_event_create_annotation_save($event, $type, $object) {
    /*
     * Viene aqui cuando comentamos (en un blog, enlace, archivo subido, página...) (crear comentario), cuando le damos a LIKE (en un blog, en un archivo subido, en un enlace, etc)
     * cuando respondemos una discusión (reply) (variable object contiene todo lo que hay que guardar ($object->name = 'generic_comment' name_id = 36) 
     * excepto el container_guid, que lo obtendremos cogiendo la entidad a la que pertenece el comentario(entity_guid),por el ejemplo, un blog. 
     * En la entidad en la que se creo el comentario ya tendremos el container_guid.
     */

    static $log_cache;
    static $cache_size = 0;

    if ($object instanceof Loggable) {
        if (strcmp($object->getSubtype(), 'generic_comment') == 0 || strcmp($object->getSubtype(), 'likes') == 0 || strcmp($object->getSubtype(), 'group_topic_post') == 0) {
            $entity_guid = $object->entity_guid;
            $entity_owner = get_entity($entity_guid);
            $actor_guid = elgg_get_logged_in_user_guid();
            $resource_type = $entity_owner->getSubtype();
            if (strcmp($object->getSubtype(), 'generic_comment') == 0) {
                $action_type = "COMMENTED";
            } elseif (strcmp($object->getSubtype(), 'likes') == 0) {
                $action_type = "LIKED";
            } elseif (strcmp($object->getSubtype(), 'group_topic_post') == 0) {
                $action_type = "RESPONSED";
            }

            if($resource_type=='comment'){

                $resource_guid = (int) $entity_owner->container_guid;
                if (isset($entity_owner->owner_guid)) {
                    $owner_guid = $entity_owner->owner_guid;
                } else {
                    $owner_guid = 0;
                }
                if (isset($entity_owner->container_guid)) {
                    $father = get_entity($entity_owner->container_guid);
                    $container_guid = $father->container_guid;
                } else {
                    $container_guid = 0;
                }

            }else{
                $resource_guid = (int) $entity_owner->getSystemLogID();
                if (isset($entity_owner->owner_guid)) {
                    $owner_guid = $entity_owner->owner_guid;
                } else {
                    $owner_guid = 0;
                }
                if (isset($entity_owner->container_guid)) {
                    $container_guid = $entity_owner->container_guid;
                } else {
                    $container_guid = 0;
                }
            }
            $time = time();

            $array_tags = $entity_owner->tags;
            if (!empty($array_tags)) {
                if (count($array_tags) == 1) {
                    $tags = (string) $array_tags;
                } else {
                    $tags = implode(",", $array_tags);
                }
            } else {
                $tags = false;
            }

            $array_hype_categories = events_collector_get_entity_categories($resource_guid, array(), true);
            if (!empty($array_hype_categories)) {
                foreach ($array_hype_categories as $hype_category_entity_guid) {
                    $hype_category_entity = get_entity($hype_category_entity_guid);
                    if (empty($categories)) {
                        $categories = $hype_category_entity->title;
                    } else {
                        $categories .= ',' . $hype_category_entity->title;
                    }
                }
            } else {
                $array_categories = $entity_owner->universal_categories;
                if (!empty($array_categories)) {
                    if (count($array_categories) == 1) {
                        $categories = (string) $array_categories;
                    } else {
                        $categories = implode(",", $array_categories);
                    }
                } else {
                    $categories = false;
                }
            }
            // Create log if we haven't already created it
            if (!isset($log_cache[$time][$resource_guid][$event])) {
                $dbprefix = elgg_get_config('dbprefix');
                $query = "INSERT DELAYED into {$dbprefix}events_log "
                        . "(actor_guid, action_type, resource_type, resource_guid, tags, categories, owner_guid, container_guid, time_created) "
                        . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$tags','$categories','$owner_guid','$container_guid','$time')";
                insert_data($query);
                $log_cache[$time][$resource_guid][$event] = true;
                $cache_size += 1;
            }
        }
    }
}

function events_collector_event_update_annotation_save($event, $type, $object) {
    /*
     * Viene aqui cuando actualizamos un comentario(en un blog, enlace, archivo subido, página...) (crear comentario),
     * cuando actualizamos una respuesta a una discusión (reply) (variable object contiene todo lo que hay que guardar ($object->name = 'generic_comment' name_id = 36) 
     * excepto el container_guid, que lo obtendremos cogiendo la entidad a la que pertenece el comentario(entity_guid),por el ejemplo, un blog. 
     * En la entidad en la que se creo el comentario ya tendremos el container_guid.
     */

    static $log_cache;
    static $cache_size = 0;

    if ($object instanceof Loggable) {

        if (strcmp($object->getSubtype(), 'generic_comment') == 0 || strcmp($object->getSubtype(), 'group_topic_post') == 0) {
            $entity_guid = $object->entity_guid;
            $entity_owner = get_entity($entity_guid);
            $actor_guid = elgg_get_logged_in_user_guid();
            $resource_type = $entity_owner->getSubtype();
            $action_type = "UPDATED";

            if($resource_type=='comment'){

                $resource_guid = (int) $entity_owner->container_guid;
                if (isset($entity_owner->owner_guid)) {
                    $owner_guid = $entity_owner->owner_guid;
                } else {
                    $owner_guid = 0;
                }
                if (isset($entity_owner->container_guid)) {
                    $father = get_entity($entity_owner->container_guid);
                    $container_guid = $father->container_guid;
                } else {
                    $container_guid = 0;
                }

            }else{
                $resource_guid = (int) $entity_owner->getSystemLogID();
                if (isset($entity_owner->owner_guid)) {
                    $owner_guid = $entity_owner->owner_guid;
                } else {
                    $owner_guid = 0;
                }
                if (isset($entity_owner->container_guid)) {
                    $container_guid = $entity_owner->container_guid;
                } else {
                    $container_guid = 0;
                }
            }
            $time = time();

            $array_tags = $entity_owner->tags;
            if (!empty($array_tags)) {
                if (count($array_tags) == 1) {
                    $tags = (string) $array_tags;
                } else {
                    $tags = implode(",", $array_tags);
                }
            } else {
                $tags = false;
            }

            $array_hype_categories = events_collector_get_entity_categories($resource_guid, array(), true);
            if (!empty($array_hype_categories)) {
                foreach ($array_hype_categories as $hype_category_entity_guid) {
                    $hype_category_entity = get_entity($hype_category_entity_guid);
                    if (empty($categories)) {
                        $categories = $hype_category_entity->title;
                    } else {
                        $categories .= ',' . $hype_category_entity->title;
                    }
                }
            } else {
                $array_categories = $entity_owner->universal_categories;
                if (!empty($array_categories)) {
                    if (count($array_categories) == 1) {
                        $categories = (string) $array_categories;
                    } else {
                        $categories = implode(",", $array_categories);
                    }
                } else {
                    $categories = false;
                }
            }
            // Create log if we haven't already created it
            if (!isset($log_cache[$time][$resource_guid][$event])) {
                $dbprefix = elgg_get_config('dbprefix');
                $query = "INSERT DELAYED into {$dbprefix}events_log "
                        . "(actor_guid, action_type, resource_type, resource_guid, tags, categories, owner_guid, container_guid, time_created) "
                        . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$tags','$categories','$owner_guid','$container_guid','$time')";
                insert_data($query);
                $log_cache[$time][$resource_guid][$event] = true;
                $cache_size += 1;
            }
        }
    }
}

function events_collector_event_delete_annotations_save($event, $type, $object) {
    /* Viene aqui cuando borramos un comentario (de un blog, enlace, archido subido, página etc), borramos un LIKE (en un enlace, etc), borramos una respuesta en una discusión (variable object contiene todo lo que hay que guardar ($object->name = 'generic_comment') 
     * excepto el container_guid, que lo obtendremos cogiendo la entidad a la que pertenece el comentario(entity_guid),por el ejemplo, un blog. En la entidad en 
     * la que se creo el comentario ya tendremos el container_guid.
     */

    static $log_cache;
    static $cache_size = 0;

    if ($object instanceof Loggable) {

        if (strcmp($object->getSubtype(), 'generic_comment') == 0 || strcmp($object->getSubtype(), 'likes') == 0 || strcmp($object->getSubtype(), 'group_topic_post') == 0) {
            $entity_guid = $object->entity_guid;
            $entity_owner = get_entity($entity_guid);
            $actor_guid = elgg_get_logged_in_user_guid();
            $resource_type = $entity_owner->getSubtype();
            if (strcmp($object->getSubtype(), 'likes') == 0) {
                $action_type = "UNLIKED";
            } else{
                $action_type = "REMOVED";
            }

            if($resource_type=='comment'){

                $resource_guid = (int) $entity_owner->container_guid;
                if (isset($entity_owner->owner_guid)) {
                    $owner_guid = $entity_owner->owner_guid;
                } else {
                    $owner_guid = 0;
                }
                if (isset($entity_owner->container_guid)) {
                    $father = get_entity($entity_owner->container_guid);
                    $container_guid = $father->container_guid;
                } else {
                    $container_guid = 0;
                }

            }else{
                $resource_guid = (int) $entity_owner->getSystemLogID();
                if (isset($entity_owner->owner_guid)) {
                    $owner_guid = $entity_owner->owner_guid;
                } else {
                    $owner_guid = 0;
                }
                if (isset($entity_owner->container_guid)) {
                    $container_guid = $entity_owner->container_guid;
                } else {
                    $container_guid = 0;
                }
            }
            $time = time();

            $array_tags = $entity_owner->tags;
            if (!empty($array_tags)) {
                if (count($array_tags) == 1) {
                    $tags = (string) $array_tags;
                } else {
                    $tags = implode(",", $array_tags);
                }
            } else {
                $tags = false;
            }

            $array_hype_categories = events_collector_get_entity_categories($resource_guid, array(), true);
            if (!empty($array_hype_categories)) {
                foreach ($array_hype_categories as $hype_category_entity_guid) {
                    $hype_category_entity = get_entity($hype_category_entity_guid);
                    if (empty($categories)) {
                        $categories = $hype_category_entity->title;
                    } else {
                        $categories .= ',' . $hype_category_entity->title;
                    }
                }
            } else {
                $array_categories = $entity_owner->universal_categories;
                if (!empty($array_categories)) {
                    if (count($array_categories) == 1) {
                        $categories = (string) $array_categories;
                    } else {
                        $categories = implode(",", $array_categories);
                    }
                } else {
                    $categories = false;
                }
            }
            // Create log if we haven't already created it
            if (!isset($log_cache[$time][$resource_guid][$event])) {
                $dbprefix = elgg_get_config('dbprefix');
                $query = "INSERT DELAYED into {$dbprefix}events_log "
                        . "(actor_guid, action_type, resource_type, resource_guid, tags, categories, owner_guid, container_guid, time_created) "
                        . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$tags','$categories','$owner_guid','$container_guid','$time')";
                insert_data($query);
                $log_cache[$time][$resource_guid][$event] = true;
                $cache_size += 1;
            }
        }
    }
}

function events_collector_event_follow_save($event, $type, $object) {
    // Viene aqui cuando seguimos a un usuario
    static $log_cache;
    static $cache_size = 0;

    if ($object instanceof Loggable) {

        // reset cache if it has grown too large
        if (!is_array($log_cache) || $cache_size > 500) {
            $log_cache = array();
            $cache_size = 0;
        }
        $actor_guid = (int) $object->guid_one;
        $resource_type = $object->getSubtype();
        $action_type = "FOLLOWED";
        $resource_guid = (int) $object->guid_two;
        if (isset($object->owner_guid)) {
            $owner_guid = $object->owner_guid;
        } else {
            $owner_guid = 0;
        }
        if (empty($owner_guid)) {
            $owner_guid = $actor_guid;
        }
        if (isset($object->container_guid)) {
            $container_guid = $object->container_guid;
        } else {
            $container_guid = 0;
        }
        if (empty($container_guid)) {
            $container_guid = $actor_guid;
        }
        $time = time();
        // Create log if we haven't already created it
        if (!isset($log_cache[$time][$resource_guid][$event])) {
            $dbprefix = elgg_get_config('dbprefix');
            $query = "INSERT DELAYED into {$dbprefix}events_log "
                    . "(actor_guid, action_type, resource_type, resource_guid, owner_guid, container_guid, time_created) "
                    . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$owner_guid','$container_guid','$time')";

            insert_data($query);
            $log_cache[$time][$resource_guid][$event] = true;
            $cache_size += 1;
        }
    }
}

function events_collector_event_unfollow_save($event, $type, $object) {
    // Viene aqui cuando dejamos de seguir a un usuario
    static $log_cache;
    static $cache_size = 0;

    if ($object instanceof Loggable) {

        // reset cache if it has grown too large
        if (!is_array($log_cache) || $cache_size > 500) {
            $log_cache = array();
            $cache_size = 0;
        }
        $actor_guid = (int) $object->guid_one;
        $resource_type = $object->getSubtype();
        $action_type = "UNFOLLOWED";
        $resource_guid = (int) $object->guid_two;
        if (isset($object->owner_guid)) {
            $owner_guid = $object->owner_guid;
        } else {
            $owner_guid = 0;
        }
        if (empty($owner_guid)) {
            $owner_guid = $actor_guid;
        }
        if (isset($object->container_guid)) {
            $container_guid = $object->container_guid;
        } else {
            $container_guid = 0;
        }
        if (empty($container_guid)) {
            $container_guid = $actor_guid;
        }
        $time = time();
        // Create log if we haven't already created it
        if (!isset($log_cache[$time][$resource_guid][$event])) {
            $dbprefix = elgg_get_config('dbprefix');
            $query = "INSERT DELAYED into {$dbprefix}events_log "
                    . "(actor_guid, action_type, resource_type, resource_guid, owner_guid, container_guid, time_created) "
                    . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$owner_guid','$container_guid','$time')";

            insert_data($query);
            $log_cache[$time][$resource_guid][$event] = true;
            $cache_size += 1;
        }
    }
}

function events_collector_route_handler($hook, $type, $returnvalue, $params) {
    /*
     * $returnvalue -> segments[0] = "view"  $returnvalue -> segments[1] = guid del recurso  $returnvalue -> segments[1] = titulo del recurso visto
     */
    static $log_cache;
    static $cache_size = 0;
    if ($returnvalue["segments"][0] == "view") {
        $entity_guid = $returnvalue["segments"][1]; //-> segments[1];
        $entity = get_entity($entity_guid);
        $actor_guid = elgg_get_logged_in_user_guid();
        $action_type = "VIEWED";
        $resource_type = $entity->getSubtype();
        $resource_guid = (int) $entity->getSystemLogID();
        if (isset($entity->owner_guid)) {
            $owner_guid = $entity->owner_guid;
        } else {
            $owner_guid = 0;
        }
        if (isset($entity->container_guid)) {
            $container_guid = $entity->container_guid;
        } else {
            $container_guid = 0;
        }
        $time = time();

        $array_tags = $entity->tags;
        if (!empty($array_tags)) {
            if (count($array_tags) == 1) {
                $tags = (string) $array_tags;
            } else {
                $tags = implode(",", $array_tags);
            }
        } else {
            $tags = false;
        }

        $array_hype_categories = events_collector_get_entity_categories($resource_guid, array(), true);
        if (!empty($array_hype_categories)) {
            foreach ($array_hype_categories as $hype_category_entity_guid) {
                $hype_category_entity = get_entity($hype_category_entity_guid);
                if (empty($categories)) {
                    $categories = $hype_category_entity->title;
                } else {
                    $categories .= ',' . $hype_category_entity->title;
                }
            }
        } else {
            $array_categories = $entity->universal_categories;
            if (!empty($array_categories)) {
                if (count($array_categories) == 1) {
                    $categories = (string) $array_categories;
                } else {
                    $categories = implode(",", $array_categories);
                }
            } else {
                $categories = false;
            }
        }
    
        if (!isset($log_cache[$time][$resource_guid][$event])) {
            $dbprefix = elgg_get_config('dbprefix');
            $query = "INSERT DELAYED into {$dbprefix}events_log "
                    . "(actor_guid, action_type, resource_type, resource_guid, tags, categories, owner_guid, container_guid, time_created) "
                    . "VALUES ('$actor_guid','$action_type','$resource_type','$resource_guid','$tags','$categories','$owner_guid','$container_guid','$time')";

            insert_data($query);
            $log_cache[$time][$resource_guid][$event] = true;
            $cache_size += 1;
        }
    }
}

/**
 * called just before a page starts with output
 * Esta función cambia el item eventsbrowser a los administradores de un grupo cuando estan viendo la página del grupo
 * El item eventsbrowser pasa de hacer referencia a la página events_collector/eventsbrowser?user_guid={$user_guid} a hacer 
 * referencia a la página events_collector/eventsbrowser?container_guid={$group_guid}
 *
 * @return void
 */
function group_pagesetup() {

    $page_owner = elgg_get_page_owner_entity();
    if (elgg_in_context("groups") && ($page_owner instanceof ElggGroup)) {
        $group_guid = $page_owner->getGUID(); //GUID del grupo
        $menuitem = elgg_get_menu_item('site','eventsbrowser');
        if (events_collector_is_logged_in_group_admin($group_guid))
            $menuitem->setHref("events_collector/eventsbrowser?container_guid={$group_guid}");
        else {
            $user_guid = elgg_get_logged_in_user_guid();
            $menuitem->setHref("events_collector/eventsbrowser?user_guid={$user_guid}&container_guid={$group_guid}");
        }
    }
}
