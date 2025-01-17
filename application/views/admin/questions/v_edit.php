<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
		echo "<pre>";
		print_r();
		echo "</pre>";
		die;
*/
?>

<style>.box:not(:first-child){margin: 30px 0;}
div.book-save.question-save {
	position: absolute;
	left: 0;
	bottom: 15px;
}
</style>
<script src="<?php echo  base_url() ?>/js/_admin/question.js"></script>

<div id="result"></div>


<div style="width:90%;padding-left:10px;margin:auto;">
	
	<h2><?php echo  $_title ?></h2>
	<p></p>
	<div class="box">
		<div class="box-title"><i class="fa fa-question"></i> متن پشتیبانی </div>
		<div class="box-content" style="padding:0 15px">
					<form class="has-feedback book-part question-part row <?php echo  @$question->sound != '' ? 'has-sound':'' ?> <?php echo  @$question->image != '' ? 'has-image':'' ?>" data-id="<?php echo  @$question->qid ?>">
						<div class="col-xs-1">
							<ul class="btn-group-vertical list-unstyled">
								<li class="btn btn-default add-sound" title="افزودن یا حذف صدا"><i class="fa fa-play-circle-o"></i></li>
								<li class="btn btn-default add-image" title="افزودن یا حذف تصویر"><i class="fa fa-picture-o"></i></li>
							</ul>
						</div>

						<div class="part-content col-xs-10">
                            <select id="catid" class="input small" name="catid">
								<option value="0"<?php echo  intval(@$question->catid)==0?" selected":"";?>>نامشخص</option>
                                <?php foreach($catquest as $k=>$v){ ?>
								<option value="<?php echo $v->id;?>"<?php echo  intval(@$question->catid)==$v->id?" selected":"";?>><?php echo $v->title;?></option>
								<?php } ?>
                            </select>
							<textarea name="content" class="form-control part-text" placeholder="متن"><?php echo  @$question->content ?></textarea>
							<div class="part-sound pull-left">
								<input name="file" type="hidden" value="<?php echo  @$question->sound ?>">
								<i class="fa fa-volume-up toggle-sound" title="مشاهده یا پنهان کردن فایل صوتی"></i>
								<a href="<?php echo  @$question->sound ?>" download="<?php echo  @$question->sound ?>"><i class="fa fa-download" title="دریافت فایل صوتی"></i></a>
							</div>
							<div class="part-image pull-left">
								<input name="image" type="hidden" value="<?php echo  @$question->image ?>">
								<i class="fa fa-picture-o toggle-image" title="مشاهده یا پنهان کردن تصویر"></i>
								<a href="<?php echo  @$question->image ?>" download="<?php echo  @$question->image ?>"><i class="fa fa-download" title="دریافت تصویر"></i></a>
							</div>
							<input name="master"	class="part-master" type="hidden"	value="1" />
							<input name="id"    	class="part-id"		type="hidden" value="<?php echo  intval(@$question->id);?>">
							<input name="qid"		class="part-mid"	type="hidden" value="<?php echo  intval(@$question->qid);?>" />
						</div>
						<div class="col-xs-1">
							<ul class="btn-group-vertical list-unstyled">
								<li class="btn btn-default delete-part" title="حذف پاراگراف"><i class="fa fa-trash"></i></li>
							</ul>
						</div>
						<div class="book-save question-save" title="ذخیره"></div>
					</form>
		</div>
	</div>
	<div class="box">
		<div class="box-title"><i class="fa fa-question"></i> پاسخها </div>
		<div class="box-content" style="padding:0 15px">

			<?php if(isset($questiondetail) && is_array($questiondetail) && !empty($questiondetail)): ?>
				<?php foreach ($questiondetail as $pk=>$p): ?>
					<form class="has-feedback book-part question-part row <?php echo  $p->sound != '' ? 'has-sound':'' ?> <?php echo  $p->image != '' ? 'has-image':'' ?>" data-id="<?php echo  $p->qid ?>">
						<div class="col-xs-1">
							<ul class="btn-group-vertical list-unstyled">
								<li class="btn btn-default add-sound" title="افزودن یا حذف صدا"><i class="fa fa-play-circle-o"></i></li>
								<li class="btn btn-default add-image" title="افزودن یا حذف تصویر"><i class="fa fa-picture-o"></i></li>
								<li class="btn btn-default add-part" title="افزودن پاراگراف"><i class="fa fa-plus-circle"></i></li>
							</ul>
						</div>

						<div class="part-content col-xs-10">
							<textarea name="content" class="form-control part-text" placeholder="متن"><?php echo  $p->content ?></textarea>
							<div class="part-sound pull-left">
								<input name="file" type="hidden" value="<?php echo  $p->sound ?>">
								<i class="fa fa-volume-up toggle-sound" title="مشاهده یا پنهان کردن فایل صوتی"></i>
								<a href="<?php echo  $p->sound ?>" download="<?php echo  $p->sound ?>"><i class="fa fa-download" title="دریافت فایل صوتی"></i></a>
							</div>
							<div class="part-image pull-left">
								<input name="image" type="hidden" value="<?php echo  $p->image ?>">
								<i class="fa fa-picture-o toggle-image" title="مشاهده یا پنهان کردن تصویر"></i>
								<a href="<?php echo  $p->image ?>" download="<?php echo  $p->image ?>"><i class="fa fa-download" title="دریافت تصویر"></i></a>
							</div>
							<input name="master" class="part-master" type="hidden" value="0" />
							<input name="id"    class="part-id"		type="hidden" value="<?php echo  $p->id;?>">
							<input name="qid"	class="part-qid"	type="hidden" value="<?php echo  $p->qid;?>" />
						</div>
						<div class="col-xs-1">
							<ul class="btn-group-vertical list-unstyled">
								<li class="btn btn-default level-up"    title="انتقال به بالا"><i class="fa fa-angle-up"></i></li>
								<li class="btn btn-default part-grid"   title="تغییر نحوه نمایش"><i class="fa fa-th-list"></i></li>
								<li class="btn btn-default delete-part" title="حذف پاراگراف"><i class="fa fa-trash"></i></li>
								<li class="btn btn-default level-down"  title="انتقال به پایین"><i class="fa fa-angle-down"></i></li>
							</ul>
						</div>
						<div class="book-save question-save" title="ذخیره"></div>
					</form>
				<?php endforeach ?>
			<?php else : ?>
				<form class="has-feedback book-part question-part row">
					<div class="col-xs-1">
						<ul class="btn-group-vertical list-unstyled">
							<li class="btn btn-default add-sound" title="افزودن یا حذف صدا"><i class="fa fa-play-circle-o"></i></li>
							<li class="btn btn-default add-image" title="افزودن یا حذف تصویر"><i class="fa fa-picture-o"></i></li>
							<li class="btn btn-default add-part" title="افزودن پاراگراف"><i class="fa fa-plus-circle"></i></li>
						</ul>
					</div>

					<div class="part-content col-xs-10">
						<textarea name="content" class="form-control part-text" placeholder="متن"></textarea>
						<div class="part-sound pull-left">
							<input name="file" type="hidden" value="">
							<i class="fa fa-volume-up toggle-sound" title="مشاهده یا پنهان کردن فایل صوتی"></i>
							<a href="#" download="#"><i class="fa fa-download" title="دریافت فایل صوتی"></i></a>
						</div>
						<div class="part-image pull-left">
							<input name="image" type="hidden" value="">
							<i class="fa fa-picture-o toggle-image" title="مشاهده یا پنهان کردن تصویر"></i>
							<a href="#" download="#"><i class="fa fa-download" title="دریافت تصویر"></i></a>
						</div>
						<input name="master"	type="hidden"	class="part-master" value="0" />
						<input name="id"		type="hidden"	class="part-id" value="0" />
						<input name="qid"		type="hidden"	class="part-qid" value="<?php echo  intval(@$question->id);?>" />
					</div>
					<div class="col-xs-1">
						<ul class="btn-group-vertical list-unstyled">
							<li class="btn btn-default level-up" title="انتقال به بالا"><i class="fa fa-angle-up"></i></li>
							<li class="btn btn-default part-grid" title="تغییر نحوه نمایش"><i class="fa fa-th-list"></i></li>
							<li class="btn btn-default delete-part" title="حذف پاراگراف"><i class="fa fa-trash"></i></li>
							<li class="btn btn-default level-down" title="انتقال به پایین"><i class="fa fa-angle-down"></i></li>
						</ul>
					</div>
					<div class="book-save question-save" title="ذخیره"></div>
				</form>
				
			<?php endif ?>
		</div>
		<div class="box-footer">
			<div class="deleted-parts"></div>
		</div>
	</div>
	
	<div class="box">
		<div class="box-title"><i class="fa fa-check"></i>ذخیره</div>
		<div class="box-content">
		
			<div class="save-content">
				<button class="btn btn-success" onClick="saveAll(this)">ذخیره تغییرات</button>
				<h4 class="save-status en pull-left"></h4>
				<div class="progress bs-progress" style="margin: 15px 0 0;display:none;">
					<div class="progress-bar progress-bar-info progress-bar-striped active" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width:0%;">...</div>
				</div>
			</div>
			
		</div>
		<div class="box-footer"></div>
	</div>
	
</div>

<script type="text/javascript">
    var questiondata = <?php echo  json_encode($question) ?>, productdata = {};

    $(document).ready(function (e) {
	});
	
    window.onbeforeunload = function () {
		if($('.question-save.changed').length)
        return "بعضی از قسمتها ذخیره نشده اند. میخواهید خارج شوید؟";
    }
</script>