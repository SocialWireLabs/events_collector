<?php

/**
 * Form body for refining the events browser search.
 * Look for a particular person or in a time window.
 *
 * @uses $vars['username'] (username del actor_guid)
 * @uses $vars['action_type']
 * @uses $vars['resource_type']
 * @uses $vars['container_guid']
 * @uses $vars['timelower']
 * @uses $vars['timeupper']
 * * @uses $vars['admin_user']
 */
if (isset($vars['timelower']) && $vars['timelower']) {
    $lowerval = date('r', $vars['timelower']);
} else {
    $lowerval = "";
}
if (isset($vars['timeupper']) && $vars['timeupper']) {
    $upperval = date('r', $vars['timeupper']);
} else {
    $upperval = "";
}
$action_type = elgg_extract('action_type', $vars);
$resource_type = elgg_extract('resource_type', $vars);
$container_guid = elgg_extract('container_guid', $vars);
$actor_guid = elgg_extract('actor_guid', $vars);
$admin_user = elgg_extract('admin_user', $vars);

$dbprefix = elgg_get_config('dbprefix');

if ($admin_user) {//Si el usuario es administrador de la página o de un grupo podrá ver los eventos de otros usuarios (Podrá seleccionar los eventos de otros usuarios)

    $query_actor_guid = "SELECT DISTINCT actor_guid from {$dbprefix}events_log where 1";
    $result_actor_guid = get_data($query_actor_guid);

    $user_guid = elgg_get_logged_in_user_guid();
    $user = get_entity($user_guid);
    $actor_guids[$user_guid] = $user->name;
    $actor_guids[''] = '';

    foreach ($result_actor_guid as $entry) {
        if (!empty($entry->actor_guid)) {
            if ($user_guid != $entry->actor_guid){
                $actor = get_entity($entry->actor_guid);
                $actor_guids[$entry->actor_guid] = $actor->name;
            }
        }
    }

    $form .= "<div>" . elgg_echo('events_collector:user') . "    ";
    $form .= elgg_view('input/dropdown', array(
                'name' => 'search_username',
                'value' => $actor_guid,
                'options_values' => $actor_guids,
            )) . "</div>";
    }

else{
     $form = "<div style=\"display: none;\">" . elgg_echo('events_collector:user') . "    ";
     $form .= elgg_view('input/dropdown', array(
                 'name' => 'search_username',
                 'value' => $username,
                 'options' => $usernames,
             )) . "</div>";
}

if (is_int($by_user_guid)) {
    $query_action_type = "SELECT DISTINCT action_type from {$dbprefix}events_log where 1 and actor_guid=$by_user_guid";
} else {
    $query_action_type = "SELECT DISTINCT action_type from {$dbprefix}events_log where 1";
}
$result_action_type = get_data($query_action_type);
$action_types = array();
foreach ($result_action_type as $entry) {
    if (!empty($entry->action_type)) {
        array_push($action_types, $entry->action_type);
    }
}
array_push($action_types, '');
$form .= "<div>" . elgg_echo('events_collector:action_type') . "    ";
$form .= elgg_view('input/dropdown', array(
            'name' => 'action_type',
            'value' => $action_type,
            'options' => $action_types,
        )) . "</div>";

if (is_int($by_user_guid)) {
    $query_resource_type = "SELECT DISTINCT resource_type from {$dbprefix}events_log where 1 and actor_guid=$by_user_guid";
} else {
    $query_resource_type = "SELECT DISTINCT resource_type from {$dbprefix}events_log where 1";
}
$result_resource_type = get_data($query_resource_type);
$resource_types = array();
foreach ($result_resource_type as $entry) {
    if (!empty($entry->resource_type)) {
        array_push($resource_types, $entry->resource_type);
    }
}
array_push($resource_types, '');
$form .= "<div>" . elgg_echo('events_collector:resource_type') . "    ";
$form .= elgg_view('input/dropdown', array(
            'name' => 'resource_type',
            'value' => $resource_type,
            'options' => $resource_types,
        )) . "</div>";

if (is_int($by_user_guid)) {
    $query_container_guid = "SELECT DISTINCT container_guid from {$dbprefix}events_log where 1 and actor_guid=$by_user_guid";
} else {
    $query_container_guid = "SELECT DISTINCT container_guid from {$dbprefix}events_log where 1";
}
$result_container_guid = get_data($query_container_guid);
$container_guids = array();
$user_guid = elgg_get_logged_in_user_guid();
foreach ($result_container_guid as $entry) {
    if (!empty($entry->container_guid)) {
        if ($user_guid != (int) $entry->container_guid) {
            $container = get_entity($entry->container_guid);
            $container_guids[$entry->container_guid] = $container->name;
        } else {
            $container_guids[$entry->container_guid] = 'Perfil';
        }
    }
}
$container_guids[''] = '';
$form .= "<div>" . elgg_echo('events_collector:container_guid') . "    ";
$form .= elgg_view('input/dropdown', array(
            'name' => 'container_guid',
            'value' => $container_guid,
            'options_values' => $container_guids,
        )) . "</div>";

$form .= "<div>" . elgg_echo('events_collector:starttime');
$form .= elgg_view('input/text', array(
            'name' => 'timelower',
            'value' => $lowerval,
        )) . "</div>";

$form .= "<div>" . elgg_echo('events_collector:endtime');
$form .= elgg_view('input/text', array(
            'name' => 'timeupper',
            'value' => $upperval,
        )) . "</div>";
$form .= '<div class="elgg-foot">';
$form .= elgg_view('input/submit', array(
    'value' => elgg_echo('search'),
        ));
$form .= '</div>';

echo $form;
