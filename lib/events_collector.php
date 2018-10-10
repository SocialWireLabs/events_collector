<?php

//Función que nos dice si un usuario es administrador de grupo
function events_collector_is_logged_in_group_admin($group_guid, $user_guid = '') {

    if (empty($user_guid)) {
        $user_guid = elgg_get_logged_in_user_guid();
    }
    $group = get_entity($group_guid);
    if (!empty($group) && elgg_instanceof($group, "group")) {
        $options = array(
            "relationship" => "group_admin",
            "relationship_guid" => $group->getGUID(),
            "inverse_relationship" => true,
            "type" => "user",
            "limit" => false,
            "list_type" => "gallery",
            "gallery_class" => "elgg-gallery-users",
            "wheres" => array("e.guid <> " . $group->owner_guid)
        );
        //Coge todos los administradores de grupo, excepto el "dueño" del grupo
        $group_admins = elgg_get_entities_from_relationship($options);
        // add owner to the beginning of the list
        array_unshift($group_admins, $group->getOwnerEntity());
    }
    if ($group_admins){
        foreach ($group_admins as $group_admin) {
            if ($group_admin->getGUID() == $user_guid) {
                return true;
            }
        }
    }
    return false;
}

/*
 * Está función recibe el guid de un recurso
 * Devuelve true si el usuario es administrador de la página o administrador del grupo
 * Si no es ningunha de las dos cosas anteriores, lo devolvera a la página anterior
 * 
 */
function events_collector_admin_gatekeeper($group_guid, $user_guid) {
    $admin_user = false; //admin_user será true para todos los usuarios que sean administradores de página y/o administradores de un grupo
//Si el usuario es el administrador podrá ver todo
    if (!elgg_is_admin_logged_in()) {
        if (!empty($group_guid)) {//Necesitamos el $group_guid para saber si un usuario es el administrador de un grupo o no
            if (!events_collector_is_logged_in_group_admin($group_guid)) { //Si el usuario es el administrador de un grupo, podrá ver los eventos de dicho grupo
                if ($user_guid != elgg_get_logged_in_user_guid()) { //Si el usuario no es ni el administrador de un grupo, ni el administrador, sólo podrá sus eventos
                    $admin_user = false;
                    $_SESSION['last_forward_from'] = current_page_url(); // Si intenta ver otros eventos, se le mostrará un mensaje de error y lo enviaremos a la página anterior
                    register_error(elgg_echo('events_collector:userrequired'));
                    forward(REFERER);
                }
            } else {
                $admin_user = true; //Usuario es administrador de un grupo
            }
        } else {
            if ($user_guid != elgg_get_logged_in_user_guid()) { //Si el usuario no es ni el administrador de un grupo, ni el administrador, sólo podrá sus eventos
                $admin_user = false;
                $_SESSION['last_forward_from'] = current_page_url(); // Si intenta ver otros eventos, se le mostrará un mensaje de error y lo enviaremos a la página anterior
                register_error(elgg_echo('events_collector:userrequired'));
                forward(REFERER);
            }
        }
    } else {
        $admin_user = true;//Usuario es administrador de la página
    }
    return $admin_user;
}

/**
 * Retrieve the system log based on a number of parameters.
 *
 * @todo too many args, and the first arg is too confusing
 *
 * @param int|array $by_user        The guid(s) of the user(s) who initiated the event.
 *                                  Use 0 for unowned entries. Anything else falsey means anyone.
 * @param string    $event          The event you are searching on.
 * @param string    $class          The class of object it effects.
 * @param string    $type           The type
 * @param string    $subtype        The subtype.
 * @param int       $limit          Maximum number of responses to return.
 * @param int       $offset         Offset of where to start.
 * @param bool      $count          Return count or not
 * @param int       $timebefore     Lower time limit
 * @param int       $timeafter      Upper time limit
 * @param int       $object_id      GUID of an object
 * @param string    $action_type    The action_type of the event.
 * @param string    $resource_type  The resource_type of the event.
 * @param string    $container_guid The resource_type of the event.
 * @return mixed
 */
function get_events($by_user = "", $event = "", $class = "", $type = "", $subtype = "", $limit = 10, $offset = 0, $count = false, $timebefore = 0, $timeafter = 0, $object_id = 0, $action_type = "", $resource_type = "", $container_guid = "") {

    $by_user_orig = $by_user;
    if (is_array($by_user) && sizeof($by_user) > 0) {
        foreach ($by_user as $key => $val) {
            $by_user[$key] = (int) $val;
        }
    } else {
        $by_user = (int) $by_user;
    }

    $event = sanitise_string($event);
    $class = sanitise_string($class);
    $type = sanitise_string($type);
    $subtype = sanitise_string($subtype);
    $action_type = sanitise_string($action_type);
    $resource_type = sanitise_string($resource_type);
    $container_guid = sanitise_string($container_guid);

    $limit = (int) $limit;
    $offset = (int) $offset;

    $where = array();

    if ($by_user_orig !== "" && $by_user_orig !== false && $by_user_orig !== null) {
        if (is_int($by_user)) {
            $where[] = "actor_guid=$by_user";
        } else if (is_array($by_user)) {
            $where [] = "actor_guid in (" . implode(",", $by_user) . ")";
        }
    }
    if ($event != "") {
        $where[] = "event='$event'";
    }
    if ($class !== "") {
        $where[] = "object_class='$class'";
    }
    if ($type != "") {
        $where[] = "object_type='$type'";
    }
    if ($subtype !== "") {
        $where[] = "object_subtype='$subtype'";
    }

    if ($timebefore) {
        $where[] = "time_created < " . ((int) $timebefore);
    }
    if ($timeafter) {
        $where[] = "time_created > " . ((int) $timeafter);
    }
    if ($object_id) {
        $where[] = "object_id = " . ((int) $object_id);
    }
    if ($action_type) {
        $where[] = "action_type = '$action_type'";
    }
    if ($resource_type) {
        $where[] = "resource_type = '$resource_type'";
    }
    if ($container_guid) {
        $where[] = "container_guid = '$container_guid'";
    }

    $select = "*";
    if ($count) {
        $select = "count(*) as count";
    }

    $dbprefix = elgg_get_config('dbprefix');
    $query = "SELECT $select from {$dbprefix}events_log where 1 ";
    foreach ($where as $w) {
        $query .= " and $w";
    }

    if (!$count) {
        $query .= " order by time_created desc";
        if ($limit != 0) {
            $query .= " limit $offset, $limit"; // Add order and limit
        }
    }

    if ($count) {
        $numrows = get_data_row($query);
        if ($numrows) {
            return $numrows->count;
        }
    } else {
        // Get events entries / Obtener eventos
        return get_data($query);
    }
    return false;
}

/**
 * Get categories an entity is filed in
 *
 * @param int $entity_guid GUID of an entity
 * @param array $params Additional parameters to be passed to the getter function
 * @param bool $as_guids Return an array of GUIDs
 * @return array Array of filed items
 */
function events_collector_get_entity_categories($entity_guid, $params = array(), $as_guids = false) {

    $defaults = array(
        'types' => 'object',
        'subtypes' => 'hjcategory',
        'reltionship' => 'filed_in',
        'inverse_relationship' => false,
        'limit' => false
    );

    $params = array_merge($defaults, $params);

    $params['relationship_guid'] = $entity_guid;

    $categories = elgg_get_entities_from_relationship($params);

    if ($as_guids && $categories) {
        foreach ($categories as $key => $category) {
            $categories[$key] = $category->guid;
        }
    }

    return $categories;
}

function order_last_activiy_links($url) {
    $asc = elgg_echo('events_collector:ordering:asc');
    $desc = elgg_echo('events_collector:ordering:desc');

    $links = '<div class="events_collector_links">';
    $links .= '<a href="?order_by=time_created&criteria=asc" title="'.$asc.'"><img src="' . $url . 'mod/events_collector/graphics/arrowup_15.png" /></a>';
    $links .= '<a href="?order_by=time_created&criteria=desc" title="'.$desc.'"><img src="' . $url . 'mod/events_collector/graphics/arrowdown_15.png" /></a>&nbsp;'.elgg_echo('Fecha').'&nbsp;&nbsp;';
    $links .= '<a href="?order_by=name&criteria=asc" title='.$asc.'><img src="' . $url . 'mod/events_collector/graphics/arrowup_15.png" /></a>';
    $links .= '<a href="?order_by=name&criteria=desc" title="'.$desc.'"><img src="' . $url . 'mod/events_collector/graphics/arrowdown_15.png" /></a>&nbsp;'.elgg_echo('Nombre').'&nbsp;&nbsp;';
    $links .= '</div>';
    $links .= '<div class="clearfloat"></div>';

    return $links;
}
