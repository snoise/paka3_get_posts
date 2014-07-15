<?php
/*
Plugin Name: Paka3GetPosts
Plugin URI: http://www.paka3.com/wpplugin
Description: Ajaxで記事一覧を取得するショートコード
Author: Shoji ENDO
Version: 0.3
Author URI:http://www.paka3.com/
*/
	
	
$a = new Paka3GetPosts ;
//$_POST['paka3getpost_count']=1;
//$a->my_action_callback();
class Paka3GetPosts{

	//get_posts引数
	private $args;


	public function __construct(){
		//get_postsの呼び出し条件(初期値)
		$this->args = array(
								'numberposts'   => 2,
								'offset'           => 0,
								'category'         => '',
								'orderby'          => 'post_date',
								'order'            => 'DESC',
								'post_type'        => 'post',
								'post_status'      => 'publish'); 

		if( is_admin() ){
			//すべてのユーザーを対象(ログイン＋非ログイン)
			//*ログインユーザ   
			add_action('wp_ajax_paka3_gp_action',array($this,'my_action_callback'));
			//*非ログインユーザ
			add_action('wp_ajax_nopriv_paka3_gp_action',array($this,'my_action_callback'));
		} else {
			add_shortcode("paka3GetPosts",array($this,'paka3_html')); 
		}

		//Javascript読み込み
		add_action( 'wp_enqueue_scripts', array($this,'my_action_javascript'),10,1);
		//スタイルシート
		add_action( 'wp_enqueue_scripts', array($this,'my_style'),9,1);
	}
   
  //###################
   //AjaxのJavascriptの指定
  //###################
   function my_action_javascript($hook_suffix) {
      wp_enqueue_script( 'paka3_submit', plugin_dir_url( __FILE__ ) . '/js/paka3_post.js', array( 'jquery' ));	
      wp_localize_script( 'paka3_submit', 'paka3Posts', array(
          'ajaxurl'       => admin_url( 'admin-ajax.php' ),
          'security'      => wp_create_nonce( get_bloginfo('url').'paka3GetPosts' ))
      );
   }

	//##################################
	//Ajaxコールバック関数
	//##################################
	public function my_action_callback(){
		if( isset($_POST['paka3getpost_count']) && isset($_POST['paka3getpost_data'])
				 && check_admin_referer( get_bloginfo('url').'paka3GetPosts','security')){

				//base64を解除→アンシリアライズする(配列)
				$args = unserialize( base64_decode( $_POST['paka3getpost_data'] )  );

				//データの設定
				$this->args['numberposts'] = $args[ 'count' ] ? $args[ 'count' ] : $this->args['count'];;
				$this->args['offset']= $_POST['paka3getpost_count'] * $this->args['numberposts'] ;
				$this->args['order']  = $args[ 'order' ] ? $args[ 'order' ] : $this->args['order'];
				$this->args['tag']  = $args[ 'tag' ] ? $args[ 'tag' ] : $this->args['tag'];
				$this->args['category'] = $args[ 'cat' ] ? $args[ 'cat' ] : $this->args['category'];


				//記事の取得
				$posts_array = get_posts( $this->args );
				$a = array();
				//ショートコードの再帰呼び出しの回避
				remove_shortcode( "paka3GetPosts" );
				//記事データの整形
				foreach ( $posts_array as $akey => $aval) {
					$a[ $akey ] = $aval ; 
					//ショートコードを有効にする
					$a[ $akey ]->post_title = $a[ $akey ]->post_title;
					$a[ $akey ]->post_content = do_shortcode( $aval->post_content  ) ;
				}
				//JSON出力
				$response = json_encode( $a );
				header( "Content-Type: application/json" );
				echo $response;
				exit;
		}else{
			die("エラー");
		}
	}

	//##################################
	//表示(ショートコードで表示されるHTML)
	//##################################
	public function paka3_html( $atts ){
		//ショートコードに定義した値を取得&デフォルト値
		extract( shortcode_atts( array(
			'count' =>2,                  //表示数:
			'order' => 'DESC' ,           //並び順:ASC/DESC,
			'tag'   => "",     //タグ(カンマ区切り)
			'cat' => "" ,     //カテゴリ(カンマ区切り)
		), $atts) );
		//設定した値を配列へ
		$args = array(
				'count'  => sprintf(esc_html("%s"),$count),
				'order'  => sprintf(esc_html("%s"),$order),
				'tag'    => sprintf(esc_html("%s"),$tag),
				'cat'  => sprintf(esc_html("%s"),$cat),
		);
		//配列をシリアライズしてbase64化する
		$args = base64_encode (serialize($args));

		//echo gzinflate( $args );
		//プラグインのパス
		$dirUrl = plugin_dir_url(__FILE__);
		$form =<<< EOS
      <h3>記事の一覧</h3>
      <div id="paka3_get_posts">
       <!-- ここに表示 -->
        <ul id="res"></ul>
        <input type=hidden id=paka3getpost_count value = "0" />
				<input type=hidden id=paka3getpost_data value = "$args" />
        <!-- このポイントで読み込み -->
        <div class="paka3_trigger"></div>

        <button type="button" class="btn" id="getPostsSubmit">続きを読み込む</button>
        <div id=loadingmessage><img src="{$dirUrl}/loadimg.gif" /></div>
      </div>
EOS;
		return $form;
	}

	//###################
	//スタイルシートの指定
	//###################
	public function my_style($hook_suffix) {
		echo <<< EOS
			<style  type="text/css">
				div#paka3_get_posts div#loadingmessage img{
					border:0;
				}
				div#paka3_get_posts ul#res{
					list-style:none;
					padding:0;margin:0
				}
			</style>
EOS;
		}
}
