<?php

$entities = $vars['entities'];
$size = $vars['size'];
$container_guid = $vars['container_guid'];
$vars = $vars['vars'];

if (!is_int($offset)) {
    $offset = (int) get_input('offset', 0);
}

// list type can be passed as request parameter
$list_type = get_input('list_type', 'list');

if (is_array($vars)) {
    // new function
    $defaults = array(
        'items' => $entities,
        'list_class' => 'elgg-list-entity',
        'full_view' => true,
        'pagination' => true,
        'list_type' => $list_type,
        'list_type_toggle' => false,
        'offset' => $offset,
        'limit' => null,
    );

    $vars = array_merge($defaults, $vars);
}

if (!$vars["limit"] && !$vars["offset"]) {
    // no need for pagination if listing is unlimited
    $vars["pagination"] = false;
}

if ($vars['list_type'] != 'list') {
    $body = elgg_view('page/components/gallery', $vars);
} else {
    $items = $vars['items'];
    $offset = elgg_extract('offset', $vars);
    $limit = elgg_extract('limit', $vars);
    $count = elgg_extract('count', $vars);
    $base_url = elgg_extract('base_url', $vars, '');
    $pagination = elgg_extract('pagination', $vars, true);
    $offset_key = elgg_extract('offset_key', $vars, 'offset');
    $position = elgg_extract('position', $vars, 'after');

    $list_class = 'elgg-list';
    if (isset($vars['list_class'])) {
        $list_class = "$list_class {$vars['list_class']}";
    }

    $item_class = 'elgg-item';
    if (isset($vars['item_class'])) {
        $item_class = "$item_class {$vars['item_class']}";
    }

    $html = "";
    $nav = "";

    if ($pagination && $count) {
        $nav .= elgg_view('navigation/pagination', array(
            'base_url' => $base_url,
            'offset' => $offset,
            'count' => $count,
            'limit' => $limit,
            'offset_key' => $offset_key,
        ));
    }

    //$items son los usuarios (ElggUser)
    if (is_array($items) && count($items) > 0) {
        $html .= "<ul class=\"$list_class\">";
        foreach ($items as $item) {

            $dbprefix = elgg_get_config('dbprefix'); 
            $query = "SELECT time_created as max_time_created, action_type, resource_type, resource_guid FROM {$dbprefix}events_log WHERE actor_guid=$item->guid AND container_guid=$container_guid ORDER BY time_created desc LIMIT 1";
            $result = get_data($query);
            foreach ($result as $entry) {
                if (empty($entry->max_time_created)) {
                    $info = elgg_echo('events_collector:no_events');
                } else {
                    $info = date("d M Y, \a \l\a\s H:i", $entry->max_time_created) . ": " . 
                                elgg_echo("events_collector:" . strtolower($entry->action_type));
                    if (!empty($entry->resource_type)){
                        $info .= " " . elgg_echo("events_collector:" . strtolower($entry->resource_type));
                    }
                    if (!empty($entry->resource_guid)){
                        $resource = get_entity($entry->resource_guid);
                        if ($resource instanceof ElggObject){
                            $info .= ": \"" . elgg_echo($resource->getDisplayName()) . "\"";
                        }  
                    }                
                }
            }
            $icon = elgg_view_entity_icon($item, $size); 
            $rel = '';
            if (elgg_get_logged_in_user_guid() == $item->guid) {
                $rel = 'rel="me"';
            } elseif (check_entity_relationship(elgg_get_logged_in_user_guid(), 'friend', $item->guid)) {
                $rel = 'rel="friend"';
            }

            $title = "<a href=\"" . $item->getUrl() . "\" $rel>" . $item->name . "</a>";

            $metadata = elgg_view_menu('entity', array(
                'entity' => $item,
                'sort_by' => 'priority',
            ));

            if (elgg_in_context('owner_block') || elgg_in_context('widgets')) {
                $metadata = '';
            }

            if ($item->isBanned()) {
                $banned = elgg_echo('banned');
                $params = array(
                    'entity' => $item,
                    'title' => $title,
                    'metadata' => $metadata,
                );
            } else {
                $params = array(
                    'entity' => $item,
                    'title' => $title,
                    'subtitle' => elgg_echo($info),
                    'content' => $item->briefdescription,
                );
            }

            $list_body = elgg_view('user/elements/summary', $params);

            $li = elgg_view_image_block($icon, $list_body, $vars);
            if ($li) {
                if (elgg_instanceof($item)) {
                    $id = "elgg-{$item->getType()}-{$item->getGUID()}";
                } else {
                    $id = "item-{$item->getType()}-{$item->id}";
                }
                $html .= "<li id=\"$id\" class=\"$item_class\">$li</li>";
            }
        }
        $html .= '</ul>';
    }

    if ($position == 'before' || $position == 'both') {
        $html = $nav . $html;
    }

    if ($position == 'after' || $position == 'both') {
        $html .= $nav;
    }

    $body = $html;
}
echo $body;

