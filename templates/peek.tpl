{if $active_worker->hasPriv('net.pixelinstrument.documents.acl.update') || $active_worker->hasPriv('net.pixelinstrument.documents.acl.upload')}
    <form enctype="multipart/form-data" action="{devblocks_url}{/devblocks_url}" method="post" id="formDocumentPeek" name="formDocumentPeek" onsubmit="return false">
        <h2>
            {if $upload}
                {$translate->_('net.pixelinstrument.documents.upload_new_document')|capitalize}
            {elseif $document->title}
                {$document->title}
            {else}
                {$translate->_('net.pixelinstrument.documents.new_document')|capitalize}
            {/if}
        </h2>
        <input type="hidden" name="c" value="documents" />
        <input type="hidden" name="a" value="saveDocumentPeek" />
        <input type="hidden" name="id" value="{if $uplaod}0{else}{$document->id}{/if}" />
        <input type="hidden" name="do_delete" value="0" />
        <input type="hidden" name="upload" value="{$upload}" />
    
        <fieldset>
            <legend>{'common.properties'|devblocks_translate}</legend>
            
            <table cellpadding="0" cellspacing="2" border="0" width="98%">
                <tr>
                    <td width="0%" nowrap="nowrap" valign="top" align="right">{$translate->_('net.pixelinstrument.documents.organization')|capitalize}: </td>
                    <td width="100%">
                        {if $org_id}
                            {$organization->name}
                            <input type="hidden" name="org_id" value="{$org_id}" />
                        {else}
                            <select name="org_id">
                               <option value="">--- {$translate->_('net.pixelinstrument.documents.select_organization')|capitalize} ---</option>
                               {foreach from=$organizations item=curOrg}
                                   <option value="{$curOrg.c_id}" {if $document->org_id == $curOrg.c_id}selected{/if}>{$curOrg.c_name}</option>
                               {/foreach}
                            </select>
                        {/if}
                    </td>
                </tr>
                
                <tr>
                    <td width="0%" nowrap="nowrap" valign="top" align="right">{$translate->_('net.pixelinstrument.documents.title')|capitalize}:</td>
                    <td width="100%">
                        <input name="title" value="{$document->title}" /><br/>
                    </td>
                </tr>
                
                <tr>
                    <td width="0%" nowrap="nowrap" valign="top" align="right">{$translate->_('net.pixelinstrument.documents.file')|capitalize}:</td>
                    <td width="100%">
                        {if $upload}
                            <input type="file" name="file" />
                        {else}
                            {$document->file_name}
                        {/if}
                    </td>
                </tr>
                
                <tr>
                    <td width="0%" nowrap="nowrap" valign="top" align="right">{'common.owners'|devblocks_translate|capitalize}: </td>
                    <td width="100%">
                        <button type="button" class="chooser_worker"><span class="cerb-sprite sprite-add"></span></button>
                        {if !empty($context_workers)}
                        <ul class="chooser-container bubbles">
                            {foreach from=$context_workers item=context_worker}
                            <li>{$context_worker->getName()|escape}<input type="hidden" name="worker_id[]" value="{$context_worker->id}"><a href="javascript:;" onclick="$(this).parent().remove();"><span class="ui-icon ui-icon-trash" style="display:inline-block;width:14px;height:14px;"></span></a></li>
                            {/foreach}
                        </ul>
                        {/if}
                    </td>
                </tr>
            </table>
        </fieldset>
        
        {if !empty($custom_fields)}
        <fieldset>
            <legend>{'common.custom_fields'|devblocks_translate}</legend>
            {include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
        </fieldset>
        {/if}
    
        <br/>
        {if $upload}
            <button type="submit" id="btn_upload"><span class="cerb-sprite sprite-check"></span> {'net.pixelinstrument.documents.upload_document'|devblocks_translate}</button>
        {else}
            <button type="submit"><span class="cerb-sprite sprite-check"></span> {$translate->_('common.save_changes')}</button>
        {/if}
        
        {if $active_worker->hasPriv('net.pixelinstrument.documents.acl.delete') && !empty($document)}
            <button type="button" onclick="if(confirm('{$translate->_('net.pixelinstrument.documents.confirm_delete')}')) { $('#formDocumentPeek input[name=do_delete]').val('1'); genericAjaxPopupPostCloseReloadView('peek', 'formDocumentPeek', '{$view_id}', false, 'document_save'); } "><span class="cerb-sprite sprite-delete2"></span> {$translate->_('common.delete')|capitalize}</button>
        {/if}
    </form>
    
    <script type="text/javascript">
        $popup = genericAjaxPopupFetch('peek');
        $popup.one('popup_open',function(event,ui) {
            $('#formDocumentPeek :input:text:first').focus().select();
            $('#formDocumentPeek :input[name=title]').css({
                'width' : '80%',
                'border' : '1px solid #AAA'
            });
        });
        
        $('#formDocumentPeek button.chooser_worker').each(function() {
            ajax.chooser(this,'cerberusweb.contexts.worker','worker_id');
        });
        
        $("#formDocumentPeek").validate( {
			rules: {
                org_id: "required",
				title: "required",
                file: {
                    required: function(element) {
                        return $('#formDocumentPeek :input[name=upload]').val() == 1;
                    }
                }
			},
			messages: {
                org_id: "{$translate->_('net.pixelinstrument.documents.error.missing_org_id')}",
				title: "{$translate->_('net.pixelinstrument.documents.error.missing_title')}",
                file: "{$translate->_('net.pixelinstrument.documents.error.missing_file')}",
            },
            submitHandler: function(form) {
                if( form.upload.value == 1 ) {
                    $('#btn_upload').html('<span class="cerb-sprite sprite-check_gray"></span> Uploading...');
                    $('#btn_upload').attr('disabled', true);
                    form.submit();
                } else {
                    genericAjaxPopupPostCloseReloadView('peek','formDocumentPeek','{$view_id}',false,'document_save');
                }
            }
		} );
        
        $("<style type='text/css'> label.error{ display: block; margin-bottom: 5px;} </style>").appendTo("head");
    </script>
{else}
    <h2>{if $document->title}{$document->title}{else}{$translate->_('net.pixelinstrument.documents.new_document')|capitalize}{/if}</h2>
    <p>{$translate->_('net.pixelinstrument.documents.error.cant_update_document')}</p>
{/if}
