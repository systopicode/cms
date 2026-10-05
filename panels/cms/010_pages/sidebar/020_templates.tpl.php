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
	<li><a data-template_name="" class='put<?= $current === '' ? ' selected' : '' ?>'>Empty Layout</a></li>
	<?php if ($current !== '' && !isset($templates_array[$current])) { ?>
		<li><a class='selected' title='Kein Template dieses Namens in templates/pages/ oder im Ordner der Site'><?= htmlspecialchars($current) ?> (fehlt in dieser Site)</a></li>
	<?php } ?>
	<?php if (!empty($templates_array)) { ?>
		<li class="seperator">Page Templates</li>
		<li>
			<?php
			$seperator = substr(reset($templates_array)->prefix, 0, 1);
			foreach ($templates_array as $name => $template) {
				$class = $current === (string) $name ? 'selected' : '';
				if ($seperator !== substr($template->prefix, 0, 1)) {
					$seperator = substr($template->prefix, 0, 1);
					echo '</li><li class="seperator" style="margin-bottom:20px;"></li><li>';
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
