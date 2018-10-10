<?php

/**
 * Events browser admin page
 *
 */

$limit = get_input('limit', 20);
$offset = get_input('offset');

$search_username = get_input('search_username');
if ($search_username) {
    $user_guid = $search_username;
} else {
    $user_guid = get_input('user_guid', null);
    if ($user_guid) {
        $user_guid = (int) $user_guid;
        $user = get_entity($user_guid);
        if ($user) {
            $search_username = $user->username;
        }
    } else {
        $user_guid = null;
    }
}

$timelower = get_input('timelower');
if ($timelower) {
    if (date_default_timezone_set('Europe/Madrid')) {
        $timelower = strtotime($timelower);
    } else {
        echo "The timezone_identifier used in the function date_default_timezone_set isn't valid";
    }
}

$timeupper = get_input('timeupper');
if ($timeupper) {
    if (date_default_timezone_set('Europe/Madrid')) {
        $timeupper = strtotime($timeupper);
    } else {
        echo "The timezone_identifier used in the function date_default_timezone_set isn't valid";
    }
}

$container_guid = get_input('container_guid', null);

$action_type = get_input('action_type');

$resource_type = get_input('resource_type');

$admin_user = events_collector_admin_gatekeeper($container_guid, $user_guid);

$refine = elgg_view('eventsbrowser/refine', array(
    'timeupper' => $timeupper,
    'timelower' => $timelower,
    'action_type' => $action_type,
    'resource_type' => $resource_type,
    'container_guid' => $container_guid,
    'actor_guid' => $search_username,
    'admin_user' => $admin_user,
        ));

// Get events entries / Obtener eventos
//Hay que limitar el tama;o de los resultados de la consulta aqui!!!!!!!!!!!!!!! Asi como tener en cuenta el offset
$events = get_events($user_guid, "", "", "", "", $limit, $offset, false, $timeupper, $timelower, 0, $action_type, $resource_type, $container_guid);

$count = get_events($user_guid, "", "", "", "", $limit, $offset, true, $timeupper, $timelower, 0, $action_type, $resource_type, $container_guid);


// if user does not exist, we have no results
if ($search_username && is_null($user_guid)) {
    $events = false;
    $count = 0;
}

$table = elgg_view('eventsbrowser/table', array('events_entries' => $events, 'num_rows' => $count, 'container_guid_selected' => $container_guid));

//No pone esta barra cuando el usuario no existe if user does not exist, we have no results
$nav = elgg_view('navigation/pagination', array(
    'offset' => $offset,
    'count' => $count,
    'limit' => $limit,
        ));

$href = "events_collector/export/csv";
$user_refine = false; //Utilizamos esta variable para poner ? en el lugar correcto y sólo una vez

if (!empty($limit)) {
    if (!$user_refine) {
        $href .= "?";
        $user_refine = true;
    }
    $href .= "limit={$limit}&";
}
if (!empty($offset)) {
    if (!$user_refine) {
        $href .= "?";
        $user_refine = true;
    }
    $href .= "offset={$offset}&";
}
if (!empty($user_guid)) {
    if (!$user_refine) {
        $href .= "?";
        $user_refine = true;
    }
    $href .= "user_guid={$user_guid}&";
}
if (!empty($action_type)) {
    if (!$user_refine) {
        $href .= "?";
        $user_refine = true;
    }
    $href .= "action_type={$action_type}&";
}
if (!empty($resource_type)) {
    if (!$user_refine) {
        $href .= "?";
        $user_refine = true;
    }
    $href .= "resource_type={$resource_type}&";
}
if (!empty($container_guid)) {
    if (!$user_refine) {
        $href .= "?";
        $user_refine = true;
    }
    $href .= "container_guid={$container_guid}&";
}
if (!empty($time_lower)) {
    if (!$user_refine) {
        $href .= "?";
        $user_refine = true;
    }
    $href .= "time_lower={$time_lower}&";
}
if (!empty($time_upper)) {
    if (!$user_refine) {
        $href .= "?";
        $user_refine = true;
    }
    $href .= "time_upper={$time_upper}&";
}

$href = substr($href, 0, -1);
elgg_register_menu_item('title', array(
    'name' => 'export_csv',
    'text' => 'Export CSV',
    'href' => $href,
    'link_class' => 'elgg-button elgg-button-action',
));

// Muestra la página
$body = <<<__HTML
$refine
$nav
$table
$nav
__HTML;

echo $body;
