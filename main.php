<?php
/*
Plugin Name: WP Code Editor
Plugin URI: http://wordpress.org/support/plugin/wp-code-editor
Description: Adds proper code editor to all code editing textareas, including the Text tab of post edit screens
Version: 0.9
Author: Davit Barbakadze
Author URI: http://wordpress.org/support/profile/jayarjo
*/

/*
Copyright (C) 2014  Davit Barbakadze

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

require_once(dirname(__FILE__) . '/i8/class.Plugino.php');


class WCE extends WCE_Plugino {

	public $pages = array(
		array(
			'title' => "WP Code Editor",
			'handle' => 'page_options',
			'parent' => 'options'
		)
	);


	public $options = array(
		'theme' => array(
			'type' => 'select_theme',
			'label' => "Theme",
			'value' => 'monokai'
		),
		'enable_emmet' => array(
			'type' => 'checkbox',
			'label' => "Enable Emmet",
			'desc' => 'Emmet greatly improves HTML & CSS workflow. More info can be found <a href="http://emmet.io/" target="_blank">here</a>.'
		)
	);


	function __construct()
	{
		parent::__construct(__FILE__);
	}


	function a__admin_footer()
	{
		?><script>
			WCE = {
				options: {
					On: <?php echo intval($this->o('enable_emmet')); ?>,
					theme: "<?php echo $this->o('theme'); ?>"
				}
			};
		</script><?php
	}


	function a__admin_enqueue_scripts()
	{
		if (in_array($GLOBALS['pagenow'], array('plugin-editor.php', 'theme-editor.php'))) 
		{
			wp_enqueue_style('wce-reset', "{$this->url}/css/reset.css", null, $this->version);
			wp_enqueue_style('wce-jstree', "{$this->url}/js/jstree/themes/default/style.min.css", null, $this->version);
			wp_enqueue_script('wce-jstree', "{$this->url}/js/jstree/jstree.min.js", array('jquery'), $this->version, true);
			$this->a__wp_enqueue_editor();
		}

	}


	function a__wp_enqueue_editor($params = array())
	{
		wp_enqueue_script('wce-ace', "{$this->url}/js/ace/ace.js", null, $this->version, true);


		if ($this->o('enable_emmet')) {
			wp_enqueue_script('wce-emmet', "https://nightwing.github.io/emmet-core/emmet.js", null, null, true);
			wp_enqueue_script('wce-ace-emmet', "{$this->url}/js/ace/ext-emmet.js", array('wce-ace'), $this->version, true);
		}

		wp_enqueue_script('wce-ace-lang-tools', "{$this->url}/js/ace/ext-language_tools.js", array('wce-ace'), $this->version, true);
		wp_enqueue_script('wce-script', "{$this->url}/js/script.js", array('jquery', 'wce-ace'), $this->version, true);
	}


	function a__wp_ajax_wce_get_files()
	{
		if ($_POST['type'] == 'plugin') {
			$base_dir = WP_PLUGIN_DIR;
			$ext = $this->get_root_dir($_POST['ext']); // extract plugin base dir only
			$files = $this->get_files("$base_dir/$ext");
		} else {
			$base_dir = preg_replace('{\/[^\/]+$}', '', TEMPLATEPATH);
			$ext = $_POST['ext'];
			$files = $this->get_files("$base_dir/$ext", 1); // theme editor allows to edit files only one level deep (why?)
		}

		$editable_extensions = (array)apply_filters( 
			'editable_extensions', 
			array('php', 'txt', 'text', 'js', 'css', 'html', 'htm', 'xml', 'inc', 'include') 
		);

		$data = array();

		foreach ($files as $file) 
		{
			$file = ltrim(str_replace($base_dir, '', $file), '/'); // file path, relative to the extensions folder

			if (!in_array(pathinfo($file, PATHINFO_EXTENSION), $editable_extensions)) {
				continue;
			}

			// generate json object for jsTree plugin
			$parent = '#';
			$path_parts = explode('/', $file);
			$root = array_shift($path_parts);

			foreach ($path_parts as $i => $part) 
			{
				$id = preg_replace('{\W+}', '-',$part);

				if ($_POST['type'] == 'plugin') {
					$is_active = $file == $_POST['file'];
					$href = add_query_arg(array(
						'file' => $file,
						'plugin' => $this->get_main_plugin_file($_POST['ext'])
					), admin_url('plugin-editor.php'));
				} else {
					$is_active = $file == "$root/{$_POST['file']}";
					$href = add_query_arg(array(
						'file' => ltrim(str_replace($root, '', $file), '/'),
						'theme' => $_POST['ext']
					), admin_url('theme-editor.php'));
				}

				$is_file = !($i < sizeof($path_parts) - 1);
				
				if (!isset($data[$id])) {
					$data[$id] = array(
						'id' => $id,
						'parent' => $parent,
						'text' => $part,
						'icon' => $is_file ? 'jstree-file' : 'jstree-folder',
						'a_attr' => $is_file ? compact('href') : new stdClass,
						'state' => $is_file && $is_active ? array('selected' => true) : new stdClass 
					);
				}

				$parent = $id;
			}
		}

		echo json_encode(array_values($data));
		exit;
	}


	// beautify html for post edit screens
	function f__the_editor_content($content)
	{
		// expand one-liner tags and indent
		if (!function_exists('htmLawed')) {
			require_once("{$this->path}/tools/htmLawed.php");
		}
		return htmLawed($content, array('tidy' => '1t1'));
	}


	function page_options()
	{
		$this->options_form();
	}


	function options_field_select_theme($name, &$o)
	{
		$themes = array();
		if (!$files = glob("{$this->path}/js/ace/theme-*")) {
			echo "Themes not available.";
			return;
		}

		?><select id="option-<?php echo $name; ?>" name="<?php $this->the_o($name); ?>" class="<?php echo $class; ?>"><?php	
		foreach ($files as $file) {
			if (preg_match('{theme\-([\w]+)\.js$}', $file, $matches)) {
				$theme = $matches[1];
				$theme_name = ucwords(str_replace('_', ' ', $theme));
				?><option value="<?php echo $theme; ?>" <?php if ($theme == $this->o($name)) echo 'selected="selected"'; ?>><?php echo $theme_name; ?></option><?php
			}
		}

		?></select><?php
	}


	private function get_files($dir, $depth = 0, $level = 0) 
	{
		$dir = rtrim($dir, '\\/');
		$result = array();
		
		foreach (scandir($dir) as $f) {
			if ($f !== '.' and $f !== '..') {
				if (is_dir("$dir/$f")) {
					if (!$depth || $level + 1 < $depth) {
						$result = array_merge($result, $this->get_files("$dir/$f", $depth, ++$level));
					}
				} else {
					$result[] = "$dir/$f";
				}
			}
		}
		return $result;
	}


	private function get_main_plugin_file($file)
	{
		foreach (get_plugins() as $plugin_entry => $data) 
		{
			if ($this->get_root_dir($plugin_entry) == $this->get_root_dir($file)) {
				return $plugin_entry;
			}
		}
		return false;
	}


	private function get_root_dir($path)
	{
		return preg_replace('{^([^\/]+)\/[\s\S]+$}', '$1', $path);
	}

}

new WCE;










