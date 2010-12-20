{$view_fields = $view->getColumnsAvailable()}
{assign var=total value=$results[1]}
{assign var=data value=$results[0]}
<table cellpadding="0" cellspacing="0" border="0" class="worklist" width="100%">
	<tr>
		<td nowrap="nowrap"><span class="title">{$view->name}</span> {if $view->id == 'search'}<a href="#{$view->id}_actions">{$translate->_('views.jump_to_actions')}</a>{/if}</td>
		<td nowrap="nowrap" align="right">
			<a href="javascript:;" onclick="genericAjaxGet('customize{$view->id}','c=internal&a=viewCustomize&id={$view->id}');toggleDiv('customize{$view->id}','block');">{$translate->_('common.customize')|lower}</a>
			{if $active_worker->hasPriv('core.home.workspaces')} | <a href="javascript:;" onclick="genericAjaxGet('{$view->id}_tips','c=internal&a=viewShowCopy&view_id={$view->id}');toggleDiv('{$view->id}_tips','block');">{$translate->_('common.copy')|lower}</a>{/if}
			{if $active_worker->hasPriv('net.pixelinstrument.documents.acl.export')} | <a href="javascript:;" onclick="genericAjaxGet('{$view->id}_tips','c=internal&a=viewShowExport&id={$view->id}');toggleDiv('{$view->id}_tips','block');">{$translate->_('common.export')|lower}</a>{/if}
			 | <a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewRefresh&id={$view->id}');"><span class="cerb-sprite sprite-refresh"></span></a>
			{if $active_worker->hasPriv('core.rss') && $active_worker->hasPriv('net.pixelinstrument.documents.acl.download')} | <a href="javascript:;" onclick="genericAjaxGet('{$view->id}_tips','c=documents&a=showViewRss&view_id={$view->id}&source=net.pixelinstrument.documents.rss');toggleDiv('{$view->id}_tips','block');"><span class="cerb-sprite sprite-rss"></span></a>{/if}
		</td>
	</tr>
</table>

<div id="{$view->id}_tips" class="block" style="display:none;margin:10px;padding:5px;">Loading...</div>
<form id="customize{$view->id}" name="customize{$view->id}" action="#" onsubmit="return false;" style="display:none;"></form>
<form id="viewForm{$view->id}" name="viewForm{$view->id}" action="{devblocks_url}{/devblocks_url}" method="post">
<input type="hidden" name="view_id" value="{$view->id}">
<input type="hidden" name="context_id" value="net.pixelinstrument.contexts.documents">
<input type="hidden" name="c" value="documents">
<input type="hidden" name="a" value="">
<input type="hidden" name="explore_from" value="0">
<table cellpadding="1" cellspacing="0" border="0" width="100%" class="worklistBody">

	{* Column Headers *}
	<thead>
	<tr>
		<th style="text-align:center"><input type="checkbox" onclick="checkAll('view{$view->id}',this.checked);this.blur();"></th>
		{foreach from=$view->view_columns item=header name=headers}
			{* start table header, insert column title and link *}
			<th nowrap="nowrap">
			<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewSortBy&id={$view->id}&sortBy={$header}');">{$view_fields.$header->db_label|capitalize}</a>
			
			{* add arrow if sorting by this column, finish table header tag *}
			{if $header==$view->renderSortBy}
				{if $view->renderSortAsc}
					<span class="cerb-sprite sprite-sort_ascending"></span>
				{else}
					<span class="cerb-sprite sprite-sort_descending"></span>
				{/if}
			{/if}
			</th>
		{/foreach}
	</tr>
	</thead>

	{* Column Data *}
	{foreach from=$data item=result key=idx name=results}

	{if $smarty.foreach.results.iteration % 2}
		{assign var=tableRowClass value="even"}
	{else}
		{assign var=tableRowClass value="odd"}
	{/if}
	<tbody onmouseover="$(this).find('tr').addClass('hover');" onmouseout="$(this).find('tr').removeClass('hover');" onclick="if(getEventTarget(event)=='TD') { var $chk=$(this).find('input:checkbox:first');if(!$chk) return;$chk.attr('checked', !$chk.is(':checked')); } ">
		<tr class="{$tableRowClass}">
			<td align="center" rowspan="2"><input type="checkbox" name="row_id[]" value="{$result.d_id}"></td>
			<td colspan="{math equation="x" x=$smarty.foreach.headers.total}">
				<a href="{if $active_worker->hasPriv('net.pixelinstrument.documents.acl.download')}{$documents_directory}{$result.d_org_id}/{$result.d_file_name|escape:'url'}{else}#{/if}" class="subject">{if !empty($result.d_title)}{$result.d_title|escape}{else}{$translate->_('net.pixelinstrument.documents.new_document')|capitalize}{/if}</a> {if $active_worker->hasPriv('net.pixelinstrument.documents.acl.update')}<a href="javascript:;" onclick="genericAjaxPopup('peek','c=documents&a=showDocumentPeek&id={$result.d_id}&view_id={$view->id}&org_id={$org_id}',null,false,'550');"><span class="ui-icon ui-icon-newwin" style="display:inline-block;vertical-align:middle;" title="{$translate->_('views.peek')}"></span></a>{/if}
				
				{$object_workers = DAO_ContextLink::getContextLinks(Model_Document::CUSTOM_DOCUMENTS, array_keys($data), CerberusContexts::CONTEXT_WORKER)}
				{if isset($object_workers.{$result.d_id})}
				<div style="display:inline;padding-left:5px;">
				{foreach from=$object_workers.{$result.d_id} key=worker_id item=worker name=workers}
					{if isset($workers.{$worker_id})}
						<span style="color:rgb(150,150,150);">
						{$workers.{$worker_id}->getName()}{if !$smarty.foreach.workers.last}, {/if}
						</span>
					{/if}
				{/foreach}
				</div>
				{/if}
			</td>
		</tr>
		<tr class="{$tableRowClass}">
		{foreach from=$view->view_columns item=column name=columns}
			{if substr($column,0,3)=="cf_"}
				{include file="devblocks:cerberusweb.core::internal/custom_fields/view/cell_renderer.tpl"}
			{elseif $column=="d_id"}
				<td>{$result.d_id}&nbsp;</td>
			{elseif $column=="d_create_date"}
				{assign var=overdue value=0}
				{if $result.d_create_date}
					{math assign=overdue equation="(t-x)" t=$timestamp_now x=$result.d_create_date format="%d"}
				{/if}
				<td title="{$result.d_create_date|devblocks_date}" style="{if $overdue > 0}color:rgb(220,0,0);font-weight:bold;{/if}">{$result.d_create_date|devblocks_prettytime}</td>
			{elseif $column=="d_worker_id"}
				<td>{if isset($workers.{$result.d_worker_id})}{$workers.{$result.d_worker_id}->getName()}{/if}</td>
			{elseif $column=="d_org_id"}
				<td>{if isset($organizations.{$result.d_org_id})}{$organizations.{$result.d_org_id}->name}{/if}</td>
			{else}
				<td>{$result.$column|escape}</td>
			{/if}
		{/foreach}
		</tr>
	</tbody>
	{/foreach}
	
</table>
<table cellpadding="2" cellspacing="0" border="0" width="100%" id="{$view->id}_actions">
	{if $total && !$org_id}
	<tr>
		<td colspan="2" valign="top">
			{if $active_worker->hasPriv('net.pixelinstrument.documents.acl.update_all')}<button type="button" onclick="genericAjaxPopup('peek','c=documents&a=showDocumentsBulkPanel&view_id={$view->id}&ids=' + Devblocks.getFormEnabledCheckboxValues('viewForm{$view->id}','row_id[]'),null,false,'500');"><span class="cerb-sprite sprite-folder_gear"></span> {'common.bulk_update'|devblocks_translate|lower}</button>{/if}
		</td>
	</tr>
	{/if}
	<tr>
		<td align="right" valign="top" nowrap="nowrap">
			{math assign=fromRow equation="(x*y)+1" x=$view->renderPage y=$view->renderLimit}
			{math assign=toRow equation="(x-1)+y" x=$fromRow y=$view->renderLimit}
			{math assign=nextPage equation="x+1" x=$view->renderPage}
			{math assign=prevPage equation="x-1" x=$view->renderPage}
			{math assign=lastPage equation="ceil(x/y)-1" x=$total y=$view->renderLimit}
			
			{* Sanity checks *}
			{if $toRow > $total}{assign var=toRow value=$total}{/if}
			{if $fromRow > $toRow}{assign var=fromRow value=$toRow}{/if}
			
			{if $view->renderPage > 0}
				<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewPage&id={$view->id}&page=0');">&lt;&lt;</a>
				<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewPage&id={$view->id}&page={$prevPage}');">&lt;{$translate->_('common.previous_short')|capitalize}</a>
			{/if}
			({'views.showing_from_to'|devblocks_translate:$fromRow:$toRow:$total})
			{if $toRow < $total}
				<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewPage&id={$view->id}&page={$nextPage}');">{$translate->_('common.next')|capitalize}&gt;</a>
				<a href="javascript:;" onclick="genericAjaxGet('view{$view->id}','c=internal&a=viewPage&id={$view->id}&page={$lastPage}');">&gt;&gt;</a>
			{/if}
		</td>
	</tr>
</table>
</form>
<br>