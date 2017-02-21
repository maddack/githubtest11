<input onclick="ExecuteCommand('image');" type="button" value="파일업로드"/>
<form action="" method="post">
<input type="hidden" name="mode" value="edit" />
<!--
textarea name과 하단 form_ckeditor의 id변수 동일해야
textarea를 ckeditor로 대체해줍니다.
기존값이 있다면 <textarea></textarea>사이에 선언
-->
<textarea name="textarea_id"><?=(@$textarea_value)?@$textarea_value:''?></textarea>
<input type="submit" value="등록" />
</form>
<?
//툴바, textarea name, 에디터 폭, 에디터 높이
//툴바를 빈칸으로 하면 FULL 툴바가 나옵니다.
//현재 선언해놓은 것은 reply와 basic인데 입맛에 맞게 선언하여 사용하면 됩니다.
echo form_ckeditor(array(
    'toolbar'        => 'reply',
    'id'              => 'textarea_id',
    'width'           => '500',
    'height'          => '300'
));
?>
<!--
툴바의 버튼을 외부로 뺄수도 있습니다.
맨 윗라인의 이미지 업로더를 버튼을 에디터 외부에 위치시키는 팁입니다.
-->
<script>
function ExecuteCommand( commandName )
{
    // Get the editor instance that we want to interact with.
    var oEditor = CKEDITOR.instances.textarea_id ;
 
    // Check the active editing mode.
    if (oEditor.mode == 'wysiwyg' )
    {
        // Execute the command.
        oEditor.execCommand( commandName ) ;
    }
    else
        alert( 'You must be on WYSIWYG mode!' ) ;
}
</script>