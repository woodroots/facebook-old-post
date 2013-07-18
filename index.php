<?php
/*
Plugin Name: facebook Old Post
Description: 過去の記事をランダムにfacebookに投稿
Author: @woodroots
Version: 1.0
Author URL: http://wood-roots.com
*/
require_once 'facebook.php';

//多次元配列を綺麗にする
function array_filter_recursive($input) { 
    foreach ($input as &$value) { 
    
        if (is_array($value)) { 
            $value = array_filter_recursive($value); 
        }

    } 
    
    return array_filter($input); 
} 

//最初
function facebook_old_post_init(){
	$config = array(
		'appId' => get_option('fop_appid'),
		'secret' => get_option('fop_appsec')
	);
	$facebook = new Facebook($config);
	return $facebook;
}
add_action('admin_init','facebook_old_post_init');


//管理画面生成
function facebook_old_post_menu(){
	add_menu_page(
	'facebook Old Post',
	'facebook Old Post',
	'administrator',
	'facebook_old_post_menu',
	'facebook_old_post_setting'
	);
}

//メニュー
add_action('admin_menu','facebook_old_post_menu');

//オプション保存
function facebook_old_post_options(){
	if(isset($_POST['submit_fop_first']) && wp_verify_nonce( $_POST['_wpnonce'], 'facebook_old_post_first') ){
		update_option('fop_appid',htmlspecialchars($_POST['fop_appid']));
		update_option('fop_appsec',htmlspecialchars($_POST['fop_appsec']));
	}
	if(isset($_POST['submit_fop_page']) && wp_verify_nonce( $_POST['_wpnonce'], 'facebook_old_post_login')){
		update_option('fop_post',array_filter_recursive($_POST['fop_post']));
		update_option('fop_interval',htmlspecialchars($_POST['fop_interval']));

		wp_clear_scheduled_hook('facebook_old_post_cron');
		wp_schedule_event(time(), 'fop_time', 'facebook_old_post_cron');
	}
	if(isset($_POST['submit_fop_destroy']) && wp_verify_nonce( $_POST['_wpnonce'], 'facebook_old_post_destroy')){
		unset($facebook);
		session_destroy();
	}
}

//アプリケーションID設定
function facebook_old_post_first(){
	echo '
		<form action="" method="post">
		<input type="hidden" name="_wpnonce" value="'.wp_create_nonce('facebook_old_post_first').'" />
		<table>
			<tr>
				<th>アプリケーションID</th>
				<td><input type="text" name="fop_appid" value="'.get_option('fop_appid').'" /></td>
			</tr>
			<tr>
				<th>アプリケーションシークレット</th>
				<td><input type="text" name="fop_appsec" value="'.get_option('fop_appsec').'" /></td>
			</tr>
		</table>
		<div>
			<input type="submit" class="button-primary" name="submit_fop_first" value="facebookにログイン" />
		</div>
		</form>
	';
}

//ログイン
function facebook_old_post_login($facebook){
	if($facebook->getUser()){
		//認証済み
		$user_profile = $facebook->api('/me');
		$uid = $user_profile["id"];
		$pages = $facebook->api("/$uid/accounts");
		
		//ページ一覧を表示
		echo '
			<style type="text/css">
				table.fop_table td,table.fop_table th {
					padding: 5px;
					border: 1px solid #ccc;
				}
				table.fop_table th {
					background: #eee;
				}
				.tacenter {
					text-align: center;
				}
			</style>
		';
		echo '
			<form action="" method="post">
			<input type="hidden" name="_wpnonce" value="'.wp_create_nonce('facebook_old_post_login').'" />
			<h2>投稿間隔</h2>
			<input type="text" name="fop_interval" value="'.get_option('fop_interval').'" />分

			<h2>投稿するページ</h2>
			<table class="fop_table">
				<tr class="table_head">
					<th>ページ名</th>
					<th class="tacenter">投稿する</th>
					<th class="tacenter">投稿しない</th>
				</tr>
		';
		foreach($pages["data"] as $page) {
			echo '
					<tr>
						<td>'.$page['name'].'</td>
				';
			if(array_key_exists ($page['id'],get_option('fop_post'))){
				echo '
					<td class="tacenter"><input type="radio" name="fop_post['.$page['id'].']" value="'.$page['access_token'].'" checked="checked" /></td>
					<td class="tacenter"><input type="radio" name="fop_post['.$page['id'].']" value="" /></td>
				';
			} else {
				echo '
					<td class="tacenter"><input type="radio" name="fop_post['.$page['id'].']" value="'.$page['access_token'].'" /></td>
					<td class="tacenter"><input type="radio" name="fop_post['.$page['id'].']" value="" checked="checked" /></td>
				';
			}
			echo '
					</tr>
			';
		}
		echo '</table>
			<p class="submit">
				<input type="submit" class="button-primary" name="submit_fop_page" value="変更内容を保存する" />
			</p>
			</form>
			
			<h3>セッションを切る</h3>
			<form action="" method="post">
			<p class="submit">
				<input type="hidden" name="_wpnonce" value="'.wp_create_nonce('facebook_old_post_destroy').'" />
				<input type="submit" class="button" name="submit_fop_destroy" value="セッションを切る" />
			</p>
			</form>
		';		
	} else {
		//認証まだ
		$params = array(
			'scope' => 'read_stream, publish_stream, status_update, manage_pages',
			//'redirect_uri' => 'http://' . $_SERVER["SERVER_NAME"] . $_SERVER["PHP_SELF"]
		);

		$loginUrl = $facebook->getLoginUrl($params);
		echo '<p>以下のボタンをクリックしてfacebookに接続してください。</p>';
		echo '<a href="'.$loginUrl.'"><img src="'.WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)).'signin.png" alt="" /></a>';
	}
	
}

//facebookページの設定画面表示
function facebook_old_post_manage(){

}

//

//管理画面表示
function facebook_old_post_setting(){
	$facebook = facebook_old_post_init();
	facebook_old_post_options();
	echo '<div class="wrap">';
	echo '<h2>facebook Old Post設定</h2>';
	
	if(!get_option('fop_appid') && !get_option('fop_appid')){
		facebook_old_post_first();
	} else {
		facebook_old_post_login($facebook);
	}
	echo '</div>';
}


//Cronで投稿
function facebook_old_post(){
	$facebook = facebook_old_post_init();

	//設定値の取得
	$settings = get_option('fop_post');

	if($settings){
		//投稿処理
		$mes = '';
		foreach($settings as $key=>$val){

			//記事の取得
			$post = get_posts(array(
				'numberposts' => 1,
				'orderby' => 'rand'
			));
			$post = $post[0];
			
			//画像用
			$image_id = get_post_thumbnail_id($post->ID);
			$image_url = wp_get_attachment_image_src($image_id, true);
			if($image_url){
				$picture = $image_url[0];
			} else {
				$picture = '';
			}
			
			$facebook->api("/$key/feed", "post", array(
				'message' => apply_filters('facebook_old_post_message',$post->post_content,$post),
				'picture' => apply_filters('facebook_old_post_image', $picture),
				'link' => apply_filters('facebook_old_post_link',get_permalink($post->ID),$post),
				'name' => apply_filters('facebook_old_post_name',$post->post_title,$post),
				'description' => apply_filters('facebook_old_post_description',$post->post_content,$post),
				'access_token' => $val
			));
		}
	}
}

//Cron登録
if(get_option('fop_interval')){
add_filter('cron_schedules','facebook_old_post_time');
function facebook_old_post_time($schedules){
	$blog_post_interval = intval(get_option('fop_interval'));

	$schedules['fop_time'] = array(
		'interval' => $blog_post_interval*60,
		'display' => __( 'fop_time' )
	);
	return $schedules;
}

add_action('facebook_old_post_cron', 'facebook_old_post');
function facebook_old_post_setcron() {
	if ( !wp_next_scheduled( 'facebook_old_post_cron' ) ) {
		wp_schedule_event(time(), 'fop_time', 'facebook_old_post_cron');
	}
}
add_action('wp', 'facebook_old_post_setcron');
}

?>