<?php
//Alireza Balvardi
/**
 * Created by Talkhabi.
 * User: nikan
 * Date: 6/23/2016
 * Time: 12:05 PM
		echo "<pre>";
		print_r();
		echo "</pre>";
		die;
 */
defined('BASEPATH') OR exit('No direct script access allowed');

ini_set('xdebug.var_display_max_depth', 20);
ini_set('xdebug.var_display_max_children', 256);
ini_set('xdebug.var_display_max_data', 1024);

?>
	<h2>فهرست کتاب <?php echo  $post['title'] ?></h2>
<div class="col-xs-12">
    <hr/>
	<button type="button" class="btn btn-primary on" onclick="$('.group-list > ul > li ul').css('display',$(this).hasClass('on')?'none':'');$(this).toggleClass('on')">نمایش یا عدم نمایش زیرمنوها</button>
	
	<hr/>
	
    <div class="group-list">
		<?php echo $parts;?>
    </div>

    <p><i class="fa fa-3x fa-plus-circle" onclick="add_child(this,'base')" style="cursor: pointer;color: #0da619"></i></p>
    <hr/>
    <div class="myborder" style="padding:3px;">
        <button type="button" class="btn btn-block btn-primary col-md-6" onclick="save_list(this)">
            <i class="fa fa-check-circle"></i>
            <span>ذخیره</span>
        </button>
        <div class="col-md-6">
			<div class="col-md-5 btn-danger center"><center>ذخیره ناموفق : <strong id="failed">0</strong></center></div>
			<div class="col-md-2"></div>
			<div class="col-md-5 btn-success center"><center>ذخیره موفق : <strong id="success">0</strong></center></div>
		</div>
        <div class="clearfix"></div>
		
    </div>
</div>

<style>

.group-list ul .li:not(:hover) .fa{
	display: none;
}
.mb-15{margin-bottom:15px;}
.myborder {
	padding: 0px 15px;
	border: solid 1px #fff;
	box-shadow: 0 0 3px rgb(0, 0, 0);
	margin-bottom: 10px;
	color: #fff;
	display: block;
	min-width: 200px;
	border-radius: 3px;
	line-height: 35px;
}

</style>

<script>
    var baseId = <?php echo  $baseId;?>;
    $(document).ready(function(e){

        sortable();
		
		$(document).on('click','.group-list ul .li .collapse-group',function(){
			
			$(this).parent().next().slideToggle();
			
		}).on('click','.group-list ul .li .delete-group',function(){
			
			delete_group(this.parentNode);
			
		}).on('click','.group-list ul .li .edit-group',function(){
			
			$(this).parent().find('input.name').focus().select();
			
		}).on('click','.group-list ul .li .add-group',function(){
			
			add_child(this.parentNode,'child');
			
		});

        $(document).on('mouseover','.group-list ul .li',function(){
			
			<?php if($this->user->can('delete_group')){ ?>
			if($(this).find('.delete-group').length == 0){
				$('<i/>',{'class':'fa fa-lg fa-trash delete-group'}).appendTo(this);
			}
			<?php } ?>
			if($(this).find('.edit-group').length == 0){
				$('<i/>',{'class':'fa fa-lg fa-pencil edit-group'}).appendTo(this);
				$('<i/>',{'class':'fa fa-lg fa-plus add-group'}).appendTo(this);
			}
			
			var $next = $(this).next();


			if($next.length && $next.is('ul')){
				
				if($(this).find('.collapse-group').length == 0)
					$('<i/>',{'class':'fa fa-lg fa-arrows-v collapse-group'}).appendTo(this);
			}else{
				$(this).find('.collapse-group').remove();
			}

        }).on('blur','.group-list ul .li > input',function(){

            var val = $.trim($(this).val());
            if( val == '' )
                $(this).parent().addClass('missing-name');
            else
                $(this).parent().removeClass('missing-name');
        });

    });

    function add_child(btn,action)
    {

        var $buttons = $('<div/>')
            .append($('<button/>').attr("action","submit").html('افزودن'))
            .append($('<button/>').html('لغو'));

        var $body = $('<div/>');

        $('<p/>').html('نام آیتم').appendTo($body);
        $('<input/>',{'type':'text','class':'input','id':'new-child-name'}).appendTo($body);

        var onSubmit = function()
        {
            $(btn).addClass('l w');
            var data;
            if( action == 'child' )
            {
                var li = $(btn).closest('li');
                data = {
                    'parent'   : $(li).attr('data-id'),
                    'position' : parseInt($(li).attr('data-position'))+1,
                    'name'     : $('#new-child-name').val(),
                    'book_id'     : <?php echo $post['id'];?>
                };
            }
            else
            {
                data = {
                    'parent'   : baseId,
                    'position' : 1,
                    'name'     : $('#new-child-name').val(),
					'book_id'     : <?php echo $post['id'];?>
                };
            }
            $.ajax({
                type: "POST",
                url: 'admin/api/addChild',
                data: data,
                dataType: "json",
                success: function (data) {

                    if (data == "login")
                    {
                        login(function () {
                            add_child(btn)
                        });
                        return;
                    }
                    if (data.done)
                    {
                        var $li = $('<li/>').attr({
                            'data-id':data.row.id,
                            'data-parent' : data.row.parent,
                            'data-position' : data.row.position,
							'book_id'     : <?php echo $post['id'];?>
                        });

                        $('<div/>',{'class':'li'}).append(
                            $('<span/>',{'class':'id'}).html(data.row.id)
                        ).append(
                            $('<input/>',{'type':'text','class':'name'}).val(data.row.name)
                        ).appendTo($li);

                        var ul;
                        
                        if( action == 'child' )
                        {
                            ul = $(btn).closest('li').children('ul');
                            if( ! $(ul).length )
                            ul = $('<ul/>').appendTo($(btn).closest('li'));
                        }
                        else
                        {
                            ul = $('.group-list > ul');
                            if( ! ul.length )
                            ul = $('<ul/>').appendTo('.group-list');
                        }
                        $(ul).append($li);
                        sortable();
                    }
                    else
                        notify(data.msg, data.status);
                    $(btn).removeClass('l w');
                },
                error: function () {
                    $(btn).removeClass('l w');
                    notify('خطا در اتصال', 2);
                }
            });

        };

        dialog_box({
            id        : 'add-child',
            name      : 'افزودن آیتم',
            body      : $body ,
            buttons   : $buttons,
            onSubmit  : onSubmit
        });

    }

    function save_list(btn)
    {
        var lis = $('.group-list .li');

        if( ! lis.length ) return;

        var position = {};
        var data = [];
        var key = true;

        lis.each(function(i,el){
            var name = $.trim($(el).find('.name').val());
            if( name == '' )
            {
                $(el).addClass('missing-name').find('.name').focus();
                key = false;
                return false;
            }
			//Alireza Balvardi Start
			$nameA = $.trim($(el).find('.name').val());
			$nameB = $.trim($(el).parent().attr('data-name'));
			if(1){
			j = data.length;
			if(typeof(position[$(el).parent().attr('data-parent')])=='undefined')
				position[$(el).parent().attr('data-parent')] = [];
			position[$(el).parent().attr('data-parent')].push(1);
            data[j] = {
			//Alireza Balvardi End
                'id'       : $(el).parent().attr('data-id'),
                'name'     : $.trim($(el).find('.name').val()),
                'parent'   : $(el).parent().attr('data-parent'),

                'position' : position[$(el).parent().attr('data-parent')].length,
				'book_id'     : <?php echo $post['id'];?>
            };
			}
        });

        if( ! key ) return;

        $(btn).prev().html('');
        $(btn).addClass('l w');
		var success = 0;
		var failed = 0;
		for(var i=0;i<data.length;i++){
			$.ajax({
				type: "POST",
				url: 'admin/api/saveList/',
				data: {data:[data[i]]},
				dataType: "json",
				success: function (data) {
	
					if (i== 0 && data == "login")
					{
						login(function () {
							save_list(btn)
						});
						return;
					}
					if (data.done){
						notify(data.msg, data.status);
						success++;
					}else{
						$(btn).prev().html(get_alert(data));
						failed++;
					}
					$('#success').html(success);
					$('#failed').html(failed);
					$(btn).removeClass('l w');
				},
				error: function () {
					$(btn).removeClass('l w');
					notify('خطا در اتصال', 2);
				}
			});
		}

    }

    function delete_group(btn){

        var id = $(btn).closest('li').attr('data-id');

        Confirm({
            url         : "deleteGroup/"+id,
            Dhtml       : 'به طور کامل حذف می شود .<br/> ادامه می دهید ؟',
            loader      : $(btn).closest('li'),
            loadercolor : 'w',
            Did         : 'deleterow_',
            success     : function(data){
                $(btn).closest('li').slideUp(500,function(){$(this).remove()});
            }
        });
    }

    function sortable()
    {
        if( $('.group-list ul.ui-sortable').length )
        $('.group-list ul').sortable('destroy');
        $('.group-list ul').sortable({
            connectWith: $('.group-list ul'),
            stop: function( event, ui ) {

                var li = $(ui.item);
                var parent = $(li).parent().parent();

                if( ! $(parent).is('[data-id]') )
                    parent = baseId;
                else
                    parent = $(parent).attr('data-id');

                $(li).attr('data-parent',parent);
            }
        });
    }
</script>
