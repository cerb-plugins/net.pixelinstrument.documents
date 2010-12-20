<form action="{devblocks_url}{/devblocks_url}" method="POST" id="formBatchUpdate" name="formBatchUpdate" onsubmit="return false;">
<input type="hidden" name="c" value="documents">
<input type="hidden" name="a" value="doDocumentsBulkUpdate">
<input type="hidden" name="view_id" value="{$view_id}">
<input type="hidden" name="ids" value="{$ids}">

<fieldset>
	<legend>{$translate->_('common.bulk_update.with')|capitalize}</legend>
	<label><input type="radio" name="filter" value="" {if empty($ids)}checked{/if}> {$translate->_('common.bulk_update.filter.all')}</label> 
 	{if !empty($ids)}
		<label><input type="radio" name="filter" value="checks" {if !empty($ids)}checked{/if}> {$translate->_('common.bulk_update.filter.checked')}</label> 
	{else}
		<label><input type="radio" name="filter" value="sample"> {'common.bulk_update.filter.random'|devblocks_translate|escape} </label><input type="text" name="filter_sample_size" size="5" maxlength="4" value="100" class="input_number">
	{/if}
</fieldset>

<fieldset>
	<legend>Set Fields</legend>
	<table cellspacing="0" cellpadding="2" width="100%">
		<tr>
			<td width="0%" nowrap="nowrap" align="right">{$translate->_('net.pixelinstrument.documents.organization')|capitalize}: </td>
			<td width="100%">
				<select name="org_id">
				   <option value="">--- {$translate->_('net.pixelinstrument.documents.select_organization')|capitalize} ---</option>
				   {foreach from=$organizations item=curOrg}
					   <option value="{$curOrg.c_id}" {if $document->org_id == $curOrg.c_id}selected{/if}>{$curOrg.c_name}</option>
				   {/foreach}
				</select>
			</td>
		</tr>

		<tr>
			<td width="0%" nowrap="nowrap" valign="top">{'common.owners'|devblocks_translate|capitalize}:</td>
			<td width="100%">
				<button type="button" class="chooser-worker add"><span class="cerb-sprite sprite-add"></span></button>
				<br>
				<button type="button" class="chooser-worker remove"><span class="cerb-sprite sprite-forbidden"></span></button>
			</td>
		</tr>
	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset>
	<legend>Set Custom Fields</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=true}	
</fieldset>
{/if}

<button type="button" onclick="genericAjaxPopupClose('peek');genericAjaxPost('formBatchUpdate','view{$view_id}');"><span class="cerb-sprite sprite-check"></span> {$translate->_('common.save_changes')|capitalize}</button>
<br>
</form>

<script type="text/javascript">
	$popup = genericAjaxPopupFetch('peek');
	$popup.one('popup_open', function(event,ui) {
		$(this).dialog('option','title',"{$translate->_('common.bulk_update')|capitalize|escape:'quotes'}");
		
		$('#formBatchUpdate button.chooser-worker').each(function() {
			$button = $(this);
			context = 'cerberusweb.contexts.worker';
			
			if($button.hasClass('remove'))
				ajax.chooser(this, context, 'do_owner_remove_ids');
			else
				ajax.chooser(this, context, 'do_owner_add_ids');
		});
	});
</script>
