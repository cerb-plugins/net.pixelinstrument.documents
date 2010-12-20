{if !$org_id && $active_worker->hasPriv('net.pixelinstrument.documents.acl.upload')}
	<form action="{devblocks_url}{/devblocks_url}" style="margin-bottom:5px;">
		<button type="button" onclick="genericAjaxPopup('peek','c=documents&a=showDocumentPeek&id=0&upload=1&view_id={$view->id}',null,false,'500');"><span class="cerb-sprite sprite-add"></span> {'net.pixelinstrument.documents.upload_document'|devblocks_translate}</button>
	</form>
{/if}

{include file="devblocks:cerberusweb.core::internal/views/search_and_view.tpl" view=$view}