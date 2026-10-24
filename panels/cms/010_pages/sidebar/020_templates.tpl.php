<?php
/**
 * The template of the page — the shared templates (templates/pages/) and
 * those of the page's own site (sites/<site>/templates/pages/); other sites'
 * templates are not offered.
 *
 * @var \Systopic\Cms\Panels\Root\Cms\Pages\Sidebar\Panel $this
 */
$current = (string) ($this->parent->page->row->templateName ?? '');
$templates_array = (array) $this->pageTemplates;
?>
<h3>Templates</h3>
<ul class="templates put" data-on_click="sidebar/updateTemplate">
	<li><a data-template_name="" class='put<?= $current === '' ? ' selected' : '' ?>'>Empty layout</a></li>
	<?php if ($current !== '' && !isset($templates_array[$current])) { ?>
		<li><a class='selected' title='No template of this name in templates/pages/ or in the site folder'><?= htmlspecialchars($current) ?> (missing in this site)</a></li>
	<?php } ?>
	<?php if (!empty($templates_array)) { ?>
		<li class="seperator">Templates</li>
		<li>
			<?php
			$seperator = substr(reset($templates_array)->prefix, 0, 1);
			foreach ($templates_array as $name => $template) {
				$class = $current === (string) $name ? 'selected' : '';
				if ($seperator !== substr($template->prefix, 0, 1)) {
					$seperator = substr($template->prefix, 0, 1);
					echo '</li><li class="seperator"></li><li>';
				}
				?>
				<div style="display:block;">
					<a class="<?= $class ?> put" data-template_name="<?= $name ?>"><?= str_replace(array('_', '-'), array(' ', ' '), $template->name) ?></a>
				</div>
				<?php
			}
		}
		?>
	</li>
</ul>
