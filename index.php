<?php

/**
 * Copyright (C) 2013-2014 ModernBB
 * Based on code by FluxBB copyright (C) 2008-2012 FluxBB
 * Based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * Licensed under GPLv3
 */

define('FORUM_ROOT', dirname(__FILE__).'/');
require FORUM_ROOT.'include/common.php';


if ($luna_user['g_read_board'] == '0')
	message($lang['No view'], false, '403 Forbidden');

// Get list of forums and topics with new posts since last visit
if (!$luna_user['is_guest'])
{
	$result = $db->query('SELECT f.id, f.last_post FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$luna_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.last_post>'.$luna_user['last_visit']) or error('Unable to fetch forum list', __FILE__, __LINE__, $db->error());

	if ($db->num_rows($result))
	{
		$forums = $new_topics = array();
		$tracked_topics = get_tracked_topics();

		while ($cur_forum = $db->fetch_assoc($result))
		{
			if (!isset($tracked_topics['forums'][$cur_forum['id']]) || $tracked_topics['forums'][$cur_forum['id']] < $cur_forum['last_post'])
				$forums[$cur_forum['id']] = $cur_forum['last_post'];
		}

		if (!empty($forums))
		{
			if (empty($tracked_topics['topics']))
				$new_topics = $forums;
			else
			{
				$result = $db->query('SELECT forum_id, id, last_post FROM '.$db->prefix.'topics WHERE forum_id IN('.implode(',', array_keys($forums)).') AND last_post>'.$luna_user['last_visit'].' AND moved_to IS NULL') or error('Unable to fetch new topics', __FILE__, __LINE__, $db->error());

				while ($cur_topic = $db->fetch_assoc($result))
				{
					if (!isset($new_topics[$cur_topic['forum_id']]) && (!isset($tracked_topics['forums'][$cur_topic['forum_id']]) || $tracked_topics['forums'][$cur_topic['forum_id']] < $forums[$cur_topic['forum_id']]) && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']))
						$new_topics[$cur_topic['forum_id']] = $forums[$cur_topic['forum_id']];
				}
			}
		}
	}
}

if ($luna_config['o_feed_type'] == '1')
	$page_head = array('feed' => '<link rel="alternate" type="application/rss+xml" href="extern.php?action=feed&amp;type=rss" title="'.$lang['RSS active topics feed'].'" />');
else if ($luna_config['o_feed_type'] == '2')
	$page_head = array('feed' => '<link rel="alternate" type="application/atom+xml" href="extern.php?action=feed&amp;type=atom" title="'.$lang['Atom active topics feed'].'" />');

$forum_actions = array();

// Display a "mark all as read" link
if (!$luna_user['is_guest'])
	$forum_actions[] = '<a href="misc.php?action=markread">'.$lang['Mark all as read'].'</a>';

$page_title = array(luna_htmlspecialchars($luna_config['o_board_title']));
define('FORUM_ALLOW_INDEX', 1);
define('FORUM_ACTIVE_PAGE', 'index');
require FORUM_ROOT.'header.php';

// Print the categories and forums
$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster, f.last_topic FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$luna_user['g_id'].') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

$cur_category = 0;
$cat_count = 0;
$forum_count = 0;
while ($cur_forum = $db->fetch_assoc($result))
{
	$moderators = '';

	if ($cur_forum['cid'] != $cur_category) // A new category since last iteration?
	{
		if ($cur_category != 0)
			echo "\t\t".'</div>'."\n".'</div>'."\n\n";

		++$cat_count;
		$forum_count = 0;

?>
<div id="idx<?php echo $cat_count ?>">
    <div class="category-box">
        <div class="row category-header">
            <div class="col-sm-7 col-xs-6"><?php echo luna_htmlspecialchars($cur_forum['cat_name']) ?></div>
            <div class="col-sm-1 hidden-xs"><p class="text-center"><?php echo $lang['Topics'] ?></p></div>
            <div class="col-sm-1 hidden-xs"><p class="text-center"><?php echo $lang['Posts table'] ?></p></div>
            <div class="col-sm-3 col-xs-6"><?php echo $lang['Last post'] ?></div>
        </div>
<?php

		$cur_category = $cur_forum['cid'];
	}

	++$forum_count;
	$item_status = ($forum_count % 2 == 0) ? 'roweven' : 'rowodd';
	$forum_field_new = '';
	$icon_type = 'icon';

	// Are there new posts since our last visit?
	if (isset($new_topics[$cur_forum['fid']]))
	{
		$item_status .= ' inew';
		$forum_field_new = '<span class="newtext">[ <a href="search.php?action=show_new&amp;fid='.$cur_forum['fid'].'">'.$lang['New posts'].'</a> ]</span>';
		$icon_type = 'icon icon-new';
	}

	// Is this a redirect forum?
	if ($cur_forum['redirect_url'] != '')
	{
		$forum_field = '<span class="redirtext">'.$lang['Link to'].'</span> <a href="'.luna_htmlspecialchars($cur_forum['redirect_url']).'" title="'.$lang['Link to'].' '.luna_htmlspecialchars($cur_forum['redirect_url']).'">'.luna_htmlspecialchars($cur_forum['forum_name']).'</a>';
		$num_topics = $num_posts = '-';
		$item_status .= ' iredirect';
		$icon_type = 'icon';
	}
	else
	{
		$forum_field = '<a href="viewforum.php?id='.$cur_forum['fid'].'">'.luna_htmlspecialchars($cur_forum['forum_name']).'</a>'.(!empty($forum_field_new) ? ' '.$forum_field_new : '');
		$num_topics = $cur_forum['num_topics'];
		$num_posts = $cur_forum['num_posts'];
	}

	if ($cur_forum['forum_desc'] != '')
		$forum_field .= "\n\t\t\t\t\t\t\t\t".'<div class="forumdesc hidden-xs">'.$cur_forum['forum_desc'].'</div>';

	// If there is a last_post/last_poster
	if ($cur_forum['last_post'] != '')
	{
		if (luna_strlen($cur_forum['last_topic']) > 30)
			$cur_forum['last_topic'] = utf8_substr($cur_forum['last_topic'], 0, 30).'...';

		$last_post = '<a href="viewtopic.php?pid='.$cur_forum['last_post_id'].'#p'.$cur_forum['last_post_id'].'">'.luna_htmlspecialchars($cur_forum['last_topic']).'</a><br /><span class="bytime  hidden-xs">'.format_time($cur_forum['last_post']).' </span><span class="byuser">'.$lang['by'].' '.luna_htmlspecialchars($cur_forum['last_poster']).'</span>';
	}
	else if ($cur_forum['redirect_url'] != '')
		$last_post = '- - -';
	else
		$last_post = $lang['Never'];

	if ($cur_forum['moderators'] != '')
	{
		$mods_array = unserialize($cur_forum['moderators']);
		$moderators = array();

		foreach ($mods_array as $mod_username => $mod_id)
		{
			if ($luna_user['g_view_users'] == '1')
				$moderators[] = '<a href="profile.php?id='.$mod_id.'">'.luna_htmlspecialchars($mod_username).'</a>';
			else
				$moderators[] = luna_htmlspecialchars($mod_username);
		}

		$moderators = "\t\t\t\t\t\t\t\t".'<p class="modlist">(<em>'.$lang['Moderated by'].'</em> '.implode(', ', $moderators).')</p>'."\n";
	}

?>
            <div class="<?php echo $item_status ?> row forum-row">
                <div class="col-sm-7 col-xs-6">
                    <div class="<?php echo $icon_type ?>"><div class="nosize"><?php echo forum_number_format($forum_count) ?></div></div>
                    <div class="tclcon">
                        <div>
                            <?php echo $forum_field."\n".$moderators ?>
                        </div>
                    </div>
                </div>
                <div class="col-sm-1 hidden-xs"><p class="text-center"><?php echo forum_number_format($num_topics) ?></p></div>
                <div class="col-sm-1 hidden-xs"><p class="text-center"><?php echo forum_number_format($num_posts) ?></p></div>
                <div class="col-sm-3 col-xs-6"><?php echo $last_post ?></div>
            </div>
<?php

}

// Did we output any categories and forums?
if ($cur_category > 0)
	echo "\t\t\t".'</div>'."\n".'</div>'."\n\n";
else
	echo '<div id="idx0" class="block"><div class="box"><div class="inbox"><p>'.$lang['Empty board'].'</p></div></div></div>';

// Collect some statistics from the database
if (file_exists(FORUM_CACHE_DIR.'cache_users_info.php'))
	include FORUM_CACHE_DIR.'cache_users_info.php';

if (!defined('FORUM_USERS_INFO_LOADED'))
{
	if (!defined('FORUM_CACHE_FUNCTIONS_LOADED'))
		require FORUM_ROOT.'include/cache.php';

	generate_users_info_cache();
	require FORUM_CACHE_DIR.'cache_users_info.php';
}

$result = $db->query('SELECT SUM(num_topics), SUM(num_posts) FROM '.$db->prefix.'forums') or error('Unable to fetch topic/post count', __FILE__, __LINE__, $db->error());
list($stats['total_topics'], $stats['total_posts']) = array_map('intval', $db->fetch_row($result));

if ($luna_user['g_view_users'] == '1')
	$stats['newest_user'] = '<a href="profile.php?id='.$stats['last_user']['id'].'">'.luna_htmlspecialchars($stats['last_user']['username']).'</a>';
else
	$stats['newest_user'] = luna_htmlspecialchars($stats['last_user']['username']);


if ($luna_config['o_show_index_stats'] == 1) {
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $lang['Board stats'] ?></h3>
    </div>
    <div class="panel-body">
		<div class="row">
			<div class="col-md-2 col-sm-4 col-xs-6"><span><?php printf($lang['No of users'], '<strong>'.forum_number_format($stats['total_users']).'</strong>') ?></span></div>
			<div class="col-md-2 col-sm-4 col-xs-6"><span><?php printf($lang['No of topics'], '<strong>'.forum_number_format($stats['total_topics']).'</strong>') ?></span></div>
			<div class="col-md-2 col-sm-4 col-xs-6"><span><?php printf($lang['No of post'], '<strong>'.forum_number_format($stats['total_posts']).'</strong>') ?></span></div>
			<div class="col-md-2 col-sm-4 col-xs-6"><span><?php printf($lang['Newest user'], $stats['newest_user']) ?></span></div>
<?php

if ($luna_config['o_users_online'] == '1')
{
	// Fetch users online info and generate strings for output
	$num_guests = 0;
	$users = array();
	$result = $db->query('SELECT user_id, ident FROM '.$db->prefix.'online WHERE idle=0 ORDER BY ident', true) or error('Unable to fetch online list', __FILE__, __LINE__, $db->error());

	while ($luna_user_online = $db->fetch_assoc($result))
	{
		if ($luna_user_online['user_id'] > 1)
		{
			if ($luna_user['g_view_users'] == '1')
				$users[] = "\n\t\t\t\t".'<a href="profile.php?id='.$luna_user_online['user_id'].'">'.luna_htmlspecialchars($luna_user_online['ident']).'</a>';
			else
				$users[] = "\n\t\t\t\t".luna_htmlspecialchars($luna_user_online['ident']);
		}
		else
			++$num_guests;
	}
	
	$num_users = count($users);
	echo "\t\t\t\t".'<div class="col-md-2 col-sm-4 col-xs-6"><span>'.sprintf($lang['Users online'], '<strong>'.forum_number_format($num_users).'</strong>').'</span></div>'."\n\t\t\t\t".'<div class="col-md-2 col-sm-4 col-xs-6"><span>'.sprintf($lang['Guests online'], '<strong>'.forum_number_format($num_guests).'</strong>').'</span></div>'."\n\t\t\t\n";
	?>
		</div>
		<div class="row">
    <?php
	if ($num_users > 0)
		echo "\t\t\t\n\t\t\t\t".'<span class="users-online"><strong>'.$lang['Online'].': </strong>'."\t\t\t\t".implode(', ', $users)."\n\t\t\t\n".'</span>';
}

?>
		</div>
	</div>
</div>
<?php
}

$footer_style = 'index';
require FORUM_ROOT.'footer.php';
