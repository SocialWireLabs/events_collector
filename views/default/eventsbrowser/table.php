<?php
/**
 * Events browser table
 *
 */
//tr -> filas
//th -> columnas primera fila
//td -> resto de columnas

$events_entries = $vars['events_entries'];
$container_guid_selected = $vars['container_guid_selected'];
?>

<table class="elgg-table">
    <tr>
        <th><?php echo elgg_echo('events_collector:time_created'); ?></th>
        <th><?php echo elgg_echo('events_collector:actor_guid'); ?></th>
        <th><?php echo elgg_echo('events_collector:action_type'); ?></th>
        <th><?php echo elgg_echo('events_collector:resource_type'); ?></th>
        <th><?php echo elgg_echo('events_collector:resource_guid'); ?></th>
        <th><?php echo elgg_echo('events_collector:owner_guid'); ?></th>
        <th><?php echo elgg_echo('events_collector:container_guid'); ?></th>
    </tr>
    <?php
    $alt = '';
    foreach ($events_entries as $entry) {
        $user = get_entity($entry->actor_guid);
        if ($user) {
            $user_link = elgg_view('output/url', array(
                'href' => $user->getURL(),
                'text' => $user->name,
                'is_trusted' => true,
            ));
            $user_guid_link = elgg_view('output/url', array(
                'href' => "admin/administer_utilities/logbrowser?user_guid={$user->guid}",
                'text' => $user->getGUID(),
                'is_trusted' => true,
            ));
        } else {
            $user_guid_link = $user_link = '&nbsp;';
        }

        $owner = get_entity($entry->owner_guid);
        if ($owner) {
            $owner_link = elgg_view('output/url', array(
                'href' => $owner->getURL(),
                'text' => $owner->name,
                'is_trusted' => true,
            ));
            $owner_guid_link = elgg_view('output/url', array(
                'href' => "admin/administer_utilities/logbrowser?user_guid={$owner->guid}",
                'text' => $owner->getGUID(),
                'is_trusted' => true,
            ));
        } else {
            $owner_guid_link = $owner_link = '&nbsp;';
        }

        $profile_event = false;
        $container = get_entity($entry->container_guid);
        if ($container instanceof ElggGroup) {//Si no está seleccionado perfil
            $container = get_entity($entry->container_guid);

            if ($container) {
                $container_link = elgg_view('output/url', array(
                    'href' => $container->getURL(),
                    'text' => $container->name,
                    'is_trusted' => true,
                ));
                $container_guid_link = elgg_view('output/url', array(
                    'href' => "admin/administer_utilities/logbrowser?user_guid={$container->guid}",
                    'text' => $container->getGUID(),
                    'is_trusted' => true,
                ));
            } else {
                $container_guid_link = $container_link = '&nbsp;';
            }
        } else {
            $profile_event = true;
        }
        $resource = get_entity($entry->resource_guid);
        if ($resource instanceof ElggUser) {
            if (is_callable(array($resource, 'getURL'))) {
                $resource_link = elgg_view('output/url', array(
                    'href' => $resource->getURL(),
                    'text' => $resource->name, 
                    'is_trusted' => true,
                ));
            } else {
                $resource_link = 'events_collector:resource:not_available';
            }
        } else {
            if (is_callable(array($resource, 'getURL'))) {
                $resource_link = elgg_view('output/url', array(
                    'href' => $resource->getURL(),
                    'text' => $resource->title, 
                    'is_trusted' => true,
                ));
            } else {
                $resource_link = 'events_collector:resource:not_available';
            }
        }
        ?>
        <tr <?php echo $alt; ?>>
            <td>
                <?php
                if (date_default_timezone_set('Europe/Madrid')) {
                    echo date("d F Y H:i:s", $entry->time_created);
                } else {
                    echo "The timezone_identifier used in the function date_default_timezone_set isn't valid";
                }
                ?>
            </td>
            <td>
                <?php
                echo elgg_view_entity_icon($user, 'tiny');
                echo "<h3>$user_link</h3>"; 
                ?>
            </td>
            <td>
                <?php echo $entry->action_type; ?>
            </td>
            <td>
                <?php echo $entry->resource_type; ?>
            </td>
            <td>
                <?php echo elgg_echo($resource_link); ?>
            </td>
            <td>                
                <?php
                echo elgg_view_entity_icon($owner, 'tiny');
                echo "<h3>$owner_link</h3>";
                ?>
            </td>
            <td>
                <?php
                if (!$profile_event) {
                    echo elgg_view_entity_icon($container, 'tiny');
                    echo "<h3>$container_link</h3>";
                }
                ?>
            </td>
        </tr>
    <?php
    $alt = $alt ? '' : 'class="alt"';
}
?>
</table>
    <?php
    $num_rows = $vars['num_rows'];
    if ($num_rows == 0) {
        echo elgg_echo('events_collector:no_result');
        return true;
    }