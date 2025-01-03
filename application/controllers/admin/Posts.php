<?php defined('BASEPATH') OR exit('No direct script access allowed');
/*
echo "<pre>";
print_r();
echo "</pre>";
die;
*/
class Posts extends CI_Controller {
	public $items = array();
	
	function __construct(){
		
		parent::__construct();
		$this->load->model('m_user','user');
		
		if(!$this->user->check_login())
		{
			redirect('admin/login');
		}
		else
		{
			$this->user->checkAccess();
		}
		$this->load->model('admin/m_post','post');			
	}
	
	public function index()
	{			
		$data = $this->settings->data;
		$data['_title'] = ' | Products ';
		$this->load->view('admin/v_header',$data);
		$this->load->view('admin/v_sidebar',$data);	
		$this->load->view('admin/v_post',$data);
		$this->load->view('admin/v_footer',$data);

	}
		
	public function addedit($type="post",$op="add",$id=NULL)
	{		
		$data = $this->settings->data;
		
		global $POST_TYPES;
		
		if( isset($POST_TYPES[$type]) )
		{
			$data['form'] = $POST_TYPES[$type]['support'];
		}
		else
		{	
			show_404();
			return;
		}
		
		switch($op)
		{
			case 'add':
			if( $this->user->can('creat_'.$type) )
			{
				$data['_title'] = ' | New '.$type;
				$this->post->deleteEmpty();
				$data['post_id'] = $this->post->addEmpty($type);
			}
			else
			{	
				show_404();
				return;
			}			
			break;
			case 'edit':
			if( $this->user->can('edit_'.$type) )
			{
				$data['_title'] = ' | Edit '.$type;
				$data['post_id'] = $id;				
			}
			else
			{	
				show_404();
				return;
			}			
			break;			
		}
		
		$data['type'] = $type;
		
		$data['meta'] = array();
		
		$post_meta = $this->post->getPostMeta($data['post_id']);
		
		if(is_array($post_meta))
		foreach($post_meta as $index => $row)
		{
			$data['meta'][$row->meta_key] = $row->meta_value; 
		}
		
		$data['nashr'] = array();
		
		$post_nashr = $this->post->getPostNashr($data['post_id']);
		
		if(is_array($post_nashr))
		foreach($post_nashr as $index => $row)
		{
			$data['nashr'][$row->nashr_key] = $row->nashr_value; 
		}
		
		$data['post']  = $this->db->where('id',$data['post_id'])->get('posts',1)->result_array();
		$data['users'] = $this->db->where('level !=','user')->get('users')->result_array();

		
		if( isset(  $data['post'][0] ) )
		{
			$data['post'] = $data['post'][0];
			
			if( $data['post']['type'] == $type )
			{
                if($type == 'book')
                {
                    $this->load->model('m_book','book');
                    //$this->load->model('m_group','group');
                    //$data['indexes'] = $this->group->creatSelect(INDEX_ID,NULL,'<select class="form-control index-select">','</select>');

                    if($op == 'edit')
                    {
						//========== Tests ========//
						$data['tests'] = $this->book->getBookTests($id);
                    }
                }
                $this->load->view('admin/v_header',$data);
				$this->load->view('admin/v_sidebar',$data);	
				$this->load->view('admin/posts/v_edit',$data);
				$this->load->view('admin/v_footer',$data);
				
			}else show_404();
			
		}else show_404();
	}
	
	public function fehrestBook($id=NULL){//Alireza Balvardi
	
		$data = $this->settings->data;
		$type = 'book';
		
		global $POST_TYPES;
		
		$data['form'] = $POST_TYPES[$type]['support'];
		
		$data['_title']  = ' | فهرست کتاب';
		$data['post_id'] = (int)$id;
		$data['type']    = $type;
		$data['meta']    = [];
		
		$post_meta = $this->post->getPostMeta($data['post_id']);
		
		if(is_array($post_meta))
		foreach($post_meta as $index => $row)
			$data['meta'][$row->meta_key] = $row->meta_value; 
		
		
		$data['post'] = $this->db->where('id',$data['post_id'])->get('posts',1)->result_array();

		
		if( isset(  $data['post'][0] ) )
		{
			$data['post'] = $data['post'][0];
			
			if( $data['post']['type'] == $type )
			{
				do{
					$this->db->select('g.*,p.title');
					$this->db->join('ci_group g','g.book_id=p.id','inner',FALSE);
					$this->db->where('p.id',(int)$id);
					$parts = $this->db->get('posts p',1)->result();
					if(!count($parts)){
						$book = $this->db->select('p.title')->where('p.id',(int)$id)->get('posts p',1)->row();
						$xdata = array(
							'name'     => $book->title,
							'book_id'   => $id,
							'parent'   => 0,
							'position' => 0
						);
						$this->db->insert('group',$xdata);
					}
				}while(!count($parts));
				$parent = 0;
				if(count($parts)){
					$parent = $parts[0]->id;
					$this->getChildren($parent);
					$this->setSubGroup();
					$data['parts'] = $this->getgroup($parent);
					/*
					$this->db->select('g.*,"" AS `text`');
					$this->db->where('g.id = ',(int)$parent);
					$parts = $this->db->get('ci_group g',1)->result();
					*/
				}

				$data['baseId'] = $parent;
                $this->load->view('admin/v_header',$data);
				$this->load->view('admin/v_sidebar',$data);	
				$this->load->view('admin/posts/v_fehrest_book',$data);
				$this->load->view('admin/v_footer',$data);
				
			}else  show_404();
			
		}else show_404();
	}
    public function getgroup($id=NULL){
        if(!$id)
        {
            show_404();return;
        }

        $data['group'] = $this->db->where('id',$id)->where('parent',0)->get('group')->row();

        if( $data['group'] ){
            $html = '<li data-id="[ID]" data-position="[POSITION]" data-name="[NAME]" data-parent="[PARENT]">
                        <div class="li"><span class="id">[ID]</span><input type="text" class="name" value="[NAME]"></div>
                        [CHILDREN]
                    </li>';
			
            return $this->creatList($html);
        }
    }
    public function creatList($html="",$start="<ul>",$end="</ul>",$list=NULL,$selected=NULL){
        if( ! $list )
        $list = $this->items;

        if( empty($list) ) return NULL;

        $res = $start;
        foreach( $list as $item )
        {
            $temp = $html;
            $temp = str_replace('[ID]',$item->id,$temp);
            $temp = str_replace('[PARENT]',$item->parent,$temp);
            $temp = str_replace('[POSITION]',$item->position,$temp);
            $temp = str_replace('[NAME]',$item->name,$temp);
            $temp = str_replace('[SELECTED]',( $selected && $item->id == $selected ) ?'selected':'',$temp);


            $children = "";
            if( isset($item->sub) )
            {
                $children = $this->creatList($html,$start,$end,$item->sub);
            }
            $temp = str_replace('[CHILDREN]',$children,$temp);
            $res .= $temp;
        }
        $res .= $end;

        return $res;
    }
    public function setSubGroup($id=NULL){
        foreach( $this->items as $item )
        {
            if( $id && $item->parent == $id )
            {
                $this->items[$id]->sub[$item->id] = $item;
                $this->setSubGroup($item->id);
                unset($this->items[$item->id]);
            }
            elseif( $id == NULL )
                $this->setSubGroup($item->id);
        }
    }
	public function getChildren($id){
		$children = $this->db->where('parent',$id)->order_by('position','asc')->get('group')->result();
		foreach($children as $item)
		{
			$this->items[$item->id] = $item;
			$this->getChildren($item->id);
		}
	}
	public function editBook($id=NULL)
	{
		$data = $this->settings->data;
		$type = 'book';
		
		global $POST_TYPES;
		
		$data['form'] = $POST_TYPES[$type]['support'];
		
		$data['_title']  = ' | ویرایش محتوای کتاب';
		$data['post_id'] = (int)$id;
		$data['type']    = $type;
		$data['meta']    = [];
		
		$post_meta = $this->post->getPostMeta($data['post_id']);
		
		if(is_array($post_meta))
		foreach($post_meta as $index => $row)
			$data['meta'][$row->meta_key] = $row->meta_value; 
		
		
		$data['post'] = $this->db->where('id',$data['post_id'])->get('posts',1)->result_array();

		
		if( isset(  $data['post'][0] ) )
		{
			$data['post'] = $data['post'][0];
			
			if( $data['post']['type'] == $type )
			{
                if($type == 'book')
                {
                    $this->load->model('m_group','group');
					$g = $this->db->select('id')->where('book_id',$data['post_id'])->order_by('id')->get('group')->row();
					$gid = @$g->id;
                    $data['indexes'] = $this->group->creatSelect($gid,NULL,'<select class="form-control index-select">','</select>');

					//========= Parts =========//
					$this->db->select('p.*, g.name as index_name');
					$this->db->join('ci_group g','g.id=p.index','left',FALSE);
					$this->db->where('p.book_id',(int)$id);
					$this->db->order_by('p.order','asc');
					$data['parts'] = $this->db->get('book_meta p')->result();

					//========= Indexes ========//
					$this->db->select('gp.id');
					$this->db->join('ci_group g','g.id=p.index','inner',FALSE);
					$this->db->join('ci_group gp','gp.id=g.parent','inner',FALSE);
					$this->db->where('p.index IS NOT NULL');
					$this->db->where('p.book_id',(int)$id);
					$index_group = $this->db->get('book_meta p',1)->row();

					if( isset($index_group->id) )
						$data['group_book_id'] = $index_group->id;
                    
                }
                $this->load->view('admin/v_header',$data);
				$this->load->view('admin/v_sidebar',$data);	
				$this->load->view('admin/posts/v_edit_book',$data);
				$this->load->view('admin/v_footer',$data);
				
			}else  show_404();
			
		}else show_404();
	}	
	
	public function category_page($post_type = NULL,$op = "view",$id = NULL)
	{		
		if( ! $this->user->can('category_'.$post_type) OR ! $post_type OR ($op == "edit" && $id == NULL) )
		{
			show_404();
			return;
		}
		$data = $this->settings->data;
		
		if($op == "edit")
		{
			$data['Cat'] = $this->db->where('id',$id)->get('category')->row();
			
			if( ! $data['Cat'] OR $data['Cat']->type != $post_type)
			{
				show_404();
				return;				
			}
		}
		else
		$data['Cat'] = NULL;		
		
		$data['_title'] = ' | '. ucwords($post_type) .' category';
		$data['type']   = $post_type;
		//$data['category'] = $this->db->where('type',$post_type)->get('category')->result();
		
		$this->load->view('admin/v_header',$data);
		$this->load->view('admin/v_sidebar',$data);	
		$this->load->view('admin/posts/v_category',$data);
		$this->load->view('admin/v_footer',$data);
	}
	
	/****************  List Of Categories  ***************/
	public function LoadSubCategories($id,$level){
        $this->db->select("*");
        $this->db->where("parent",$id);
        $this->db->where("type","book");
        $this->db->order_by("position");
		$O = $this->db->get("category")->result();
		$cats = [];
		foreach($O as $k=>$v){
			$v->name = $level?str_repeat("|__",$level)." ".$v->name:$v->name;
			$cats[$v->id] = $v;
			$subcats = $this->LoadSubCategories($v->id,$level+1);
			if(count($subcats))
				$cats = array_merge($cats,$subcats);
		}
		return $cats;
	}
	/****************  List Of Posts  ***************/
	public function viewlist($post_type = NULL , $action = 'primary')
	{
		global $POST_TYPES;
		
		if( ! isset($post_type) OR ! isset($POST_TYPES[$post_type]) OR ! $this->user->can('read_'.$post_type))
		{
			show_404();
			return;
		}
		
		$type_name = $POST_TYPES[$post_type]['g_name'];
		
		$data = $this->settings->data;
		/*=========================================
				search
		=========================================*/
		$fields = array(

			'title'   => array('name'=>'عنوان کتاب'   , 'type' => 'text'),
			'special'  => array('name'=>'اولویت'        , 'type' => 'select'),
			'category_parent_id'  => array('name'=>'سطح'        , 'type' => 'select'),
			'category'  => array('name'=>'پایه‌'        , 'type' => 'select'),
			'price'   => array('name'=>'رایگان'        , 'type' => 'select'),
			'has_sound'   => array('name'=>'دارای صوت'        , 'type' => 'select'),
			'has_video'   => array('name'=>'دارای ویدئو'        , 'type' => 'select'),
			'has_membership'   => array('name'=>'دارای اشتراک'        , 'type' => 'select'),
			'has_description'   => array('name'=>'دارای شرح'        , 'type' => 'select'),
			'has_test'   => array('name'=>'دارای آزمون چهارگزینه‌ای'        , 'type' => 'select'),
			'has_tashrihi'   => array('name'=>'دارای آزمون تشریحی'        , 'type' => 'select'),
		);

		$fields['has_sound']['options']['']  = 'بدون انتخاب';
		$fields['has_sound']['options']['NOT ISNULL(`sound`)'] = 'بلی';
		$fields['has_sound']['options']['ISNULL(`sound`)'] = 'خیر';

		$fields['has_video']['options']['']  = 'بدون انتخاب';
		$fields['has_video']['options']['NOT ISNULL(`video`)'] = 'بلی';
		$fields['has_video']['options']['ISNULL(`video`)'] = 'خیر';

		$fields['has_membership']['options']['']  = 'بدون انتخاب';
		$fields['has_membership']['options']['1'] = 'بلی';
		$fields['has_membership']['options']['0'] = 'خیر';

		$fields['has_description']['options']['']  = 'بدون انتخاب';
		$fields['has_description']['options']['NOT ISNULL(`description`)'] = 'بلی';
		$fields['has_description']['options']['ISNULL(`description`)'] = 'خیر';

		$fields['has_test']['options']['']  = 'بدون انتخاب';
		$fields['has_test']['options']['1'] = 'بلی';
		$fields['has_test']['options']['0'] = 'خیر';

		$fields['has_tashrihi']['options']['']  = 'بدون انتخاب';
		$fields['has_tashrihi']['options']['1'] = 'بلی';
		$fields['has_tashrihi']['options']['0'] = 'خیر';

		$fields['price']['options']['']  = 'بدون انتخاب';
		$fields['price']['options']['=0'] = 'بلی';
		$fields['price']['options'][' > 0'] = 'خیر';

		$fields['special']['options']['']  = 'بدون انتخاب';
		$fields['special']['options']['0']  = 'عادی';
		$fields['special']['options']['1']  = 'پیشنهادی';
		$fields['special']['options']['2']  = 'ویژه';
		$fields['special']['options']['3']  = 'خاص';

		$fields['category_parent_id']['options']['']  = 'بدون انتخاب';

		$cats = $this->LoadSubCategories(0,0);
		foreach($cats as $k=>$v){
			if(!$v->parent)
				$fields['category_parent_id']['options'][$v->id]  = $v->name;
		}

		$fields['category']['options']['']  = 'بدون انتخاب';

		$searchQuery        = "";

		$title = $this->input->get('title');
		if(strlen($title)){
			$title = str_replace(array("`",'"',"'"),'',$title);
			$searchQuery.=" AND p.title LIKE '%$title%'";
		}
		
		$category_parent_id = (int)$this->input->get('category_parent_id');
		$category = (int)$this->input->get('category');
		if($category_parent_id){
			$cats = $this->LoadSubCategories($category_parent_id,1);
			foreach($cats as $k=>$v){
				$fields['category']['options'][$v->id]  = $v->name;
			}
		}
		$data['searchHtml'] = $this->tools->createSearch($fields);

		$special = $this->input->get('special');
		if(strlen($special)){
			$searchQuery.=" AND p.special = $special";
		}
		if($category_parent_id && !$category){
			$searchQuery.=" AND p.category IN (SELECT id FROM ci_category WHERE parent=$category_parent_id)";
		}
		if($category){
			$searchQuery.=" AND p.category IN ($category)";
		}
		$price = $this->input->get('price');
		if(strlen($price)){
			$searchQuery.=" AND p.id IN (SELECT post_id FROM ci_post_meta WHERE meta_key='price' AND meta_value $price GROUP BY post_id)";
		}
		$has_sound = $this->input->get('has_sound');
		if(strlen($has_sound)){
			$searchQuery.=" AND p.id IN (SELECT book_id FROM ci_book_meta WHERE $has_sound GROUP BY book_id)";
		}
		$has_video = $this->input->get('has_video');
		if(strlen($has_video)){
			$searchQuery.=" AND p.id IN (SELECT book_id FROM ci_book_meta WHERE $has_video GROUP BY book_id)";
		}
		$has_membership = (int)$this->input->get('has_membership');
		if($has_membership){
			$searchQuery.=" AND p.has_membership = 1";
		}
		$has_description = $this->input->get('has_description');
		if(strlen($has_description)){
			$searchQuery.=" AND p.id IN (SELECT book_id FROM ci_book_meta WHERE $has_description GROUP BY book_id)";
		}

		$has_test = $this->input->get('has_test');
		if(strlen($has_test)){
			if(intval($has_test))
				$searchQuery.=" AND p.id IN (SELECT book_id FROM ci_tests GROUP BY book_id)";
			else
				$searchQuery.=" AND p.id NOT IN (SELECT book_id FROM ci_tests GROUP BY book_id)";
		}

		$has_tashrihi = $this->input->get('has_tashrihi');
		if(strlen($has_tashrihi)){
			if(intval($has_tashrihi))
				$searchQuery.=" AND p.id IN (SELECT book_id FROM ci_tashrihi GROUP BY book_id)";
			else
				$searchQuery.=" AND p.id NOT IN (SELECT book_id FROM ci_tashrihi GROUP BY book_id)";
		}
		/*========================================
				/ search
		=========================================*/
		$this->load->view('admin/v_header',$data);
		
		/*
		$O = $this->db->select('book_id,COUNT(id) C')->group_by('book_id')->get('book_meta')->result();
		$part_count = array();
		foreach($O as $k=>$v){
			$part_count[$v->book_id] = $v->C;
		}
		$data['extra']['part_count'] = $part_count;

		$O = $this->db->select('book_id,COUNT(id) C')->group_by('book_id')->get('user_books')->result();
		$count_download = array();
		foreach($O as $k=>$v){
			$count_download[$v->book_id] = $v->C;
		}
		$data['extra']['has_download'] = $count_download;

		$O = $this->db->select('book_id,COUNT(id) C')->where('sound IS NOT NULL')->group_by('book_id')->get('book_meta')->result();
		$count_sound = array();
		foreach($O as $k=>$v){
			$count_sound[$v->book_id] = $v->C;
		}
		$data['extra']['has_sound'] = $count_sound;

		$O = $this->db->select('book_id,COUNT(id) C')->where('video IS NOT NULL')->group_by('book_id')->get('book_meta')->result();
		$count_video = array();
		foreach($O as $k=>$v){
			$count_video[$v->book_id] = $v->C;
		}
		$data['extra']['has_video'] = $count_video;

		$O = $this->db->select('book_id,COUNT(id) C')->where('image IS NOT NULL')->group_by('book_id')->get('book_meta')->result();
		$count_image = array();
		foreach($O as $k=>$v){
			$count_image[$v->book_id] = $v->C;
		}
		$data['extra']['has_image'] = $count_image;

		$O = $this->db->select('book_id,COUNT(id) C')->where('description IS NOT NULL')->group_by('book_id')->get('book_meta')->result();
		$count_description = array();
		foreach($O as $k=>$v){
			$count_description[$v->book_id] = $v->C;
		}
		$data['extra']['has_description'] = $count_description;

		$O = $this->db->select('book_id,COUNT(id) C')->group_by('book_id')->get('tests')->result();
		$count_test = array();
		foreach($O as $k=>$v){
			$count_test[$v->book_id] = $v->C;
		}
		$data['extra']['has_test'] = $count_test;

		$O = $this->db->select('book_id,COUNT(id) C')->group_by('book_id')->get('tashrihi')->result();
		$count_tashrihi = array();
		foreach($O as $k=>$v){
			$count_tashrihi[$v->book_id] = $v->C;
		}
		$data['extra']['has_tashrihi'] = $count_tashrihi;

		$O = $this->db->select('post_id,meta_value C')->where("meta_key='price'")->get('post_meta')->result();
		$price = array();
		foreach($O as $k=>$v){
			$price[$v->post_id] = $v->C;
		}
		$data['extra']['price'] = $price;

		$O = $this->db->select('post_id,meta_value C')->where("meta_key='pages'")->get('post_meta')->result();
		$pages = array();
		foreach($O as $k=>$v){
			$pages[$v->post_id] = $v->C;
		}
		$data['extra']['pages'] = $pages;

		$O = $this->db->select('book_id,SUM(IF( `text` IS NULL ,0,LENGTH(`text`)))	+
			SUM(IF( `description` IS NULL ,0,LENGTH(`description`))) +	
			SUM(IF( `sound` IS NULL ,0,LENGTH(`sound`)))+
			SUM(IF( `video` IS NULL ,0,LENGTH(`video`))) +	
			SUM(IF( `image` IS NULL ,0,LENGTH(`image`))) AS C')->group_by('book_id')->get('book_meta')->result();
		$size = array();
		foreach($O as $k=>$v){
			$size[$v->book_id] = $v->C;
		}
		$data['extra']['size'] = $size;
		*/

		$O = $this->db->select('id,name')->get('category')->result();
		$categoryData = array();
		foreach($O as $k=>$v){
			$categoryData[$v->id] = $v->name;
		}

		$O = $this->db->select('p.id,c.parent C,c.name')->join('ci_category c','p.category = c.id','inner',FALSE)->get('posts p')->result();
		$category_parent_id = array();
		$category_name = array();
		foreach($O as $k=>$v){
			$category_parent_id[$v->id] = $v->C;
			$top = isset($categoryData[$v->C])?$categoryData[$v->C]:null;
			$category_name[$v->id] = $top?$categoryData[$v->C].' &nbsp; <i class="fa fa-angle-double-left"></i> &nbsp '.$v->name:$v->name;
		}
		$data['extra']['category_parent_id'] = $category_parent_id;
		$data['extra']['category_name'] = $category_name;

		$data['type'] = $post_type;
		
		$this->db->where(array('type'=>$post_type,'published'=>1));
		$data['_tabs']['primary'] = array('name'=>$type_name,'count'=>$this->db->count_all_results('posts'));
		
		$this->db->where('type',$post_type)->where('draft is not null');
		$data['_tabs']['draft'] = array('name'=>'پیش نویس','count'=>$this->db->count_all_results('posts'));		

		$this->db->where('type',$post_type)->where('published = 2');
		$data['_tabs']['test'] = array('name'=>'آماده انتشار','count'=>$this->db->count_all_results('posts'));		
		
		$this->db->where(array('type'=>$post_type,'published'=>0))->where('draft is null');
		$data['_tabs']['recyclebin'] = array('name'=>'زباله دان','count'=>$this->db->count_all_results('posts'));		
		
		$data['query'] = "";
		
		$data['query'] .= "where type='$post_type' $searchQuery";			
		
		switch($action)
		{
			case'primary':
			$data['query'] .= " and  published=1 and draft is null ";
			$ops = array('submit','delete','suspend','edit');	
			break;
			/***************/
			case'draft':
			$data['query'] .= " and  draft is not null "; 
			$ops = array('submit','delete','suspend','edit');
			break;	
			/***************/		
			case'recyclebin':
			$data['query'] .= " and  published=0  and  draft is null ";
			$ops = array('submit','delete','edit');
			break;
			case'test'://Alireza Balvardi
			$data['query'] .= " and  published=2 ";
			$ops = array('submit','delete','edit');
			break;
		}
		$data['readown'] = 1;
		if($this->user->can && $this->user->can('readown_'.$post_type,0)){//Alireza Balvardi
			$data['query'] .= " and  author= ".$this->user->user_id;
			$data['_tabs'] = [];
			$data['readown'] = 0;
		}
		$data['extra']['readown'] = $data['readown'];
		$data['options'] = [];
		if( $this->user->can('edit_'.$post_type) && in_array('edit',$ops) )
		{
			//$data['options'][] = array('name'=>'ویرایش سریع','icon'=>'pencil','click'=>'quickEdit([FLD])');
			$data['options'][] = array('name'=>'ویرایش','icon'=>'pencil','href'=>site_url("admin/$post_type/edit/[FLD]"));
            $data['user_can_edit'] = TRUE;
		}
		
		if( $this->user->can('submit_'.$post_type) && in_array('submit',$ops) && $action == 'primary')	
		$data['options'][] = array('name'=>'پیش نویس','icon'=>'square-o','click'=>'_pdtd(this,\'draft\',[FLD])');		

		if( $this->user->can('submit_'.$post_type) && in_array('submit',$ops) && $action == 'draft')	
		$data['options'][] = array('name'=>'انتشار','icon'=>'check','click'=>'_pdtd(this,\'publish\',[FLD])');		
				
		if( $this->user->can('suspend_'.$post_type) && in_array('suspend',$ops) )	
		$data['options'][] = array('name'=>'زباله دان','icon'=>'trash','click'=>'_pdtd(this,\'trash\',[FLD])');				
		
		if( $this->user->can('delete_'.$post_type) && in_array('delete',$ops) )	
		$data['options'][] = array('name'=>'حذف','icon'=>'trash-o','click'=>'_pdtd(this,\'delete\',[FLD])');
		
		$data['_title'] = ' | '. $type_name;
		
		$data['tableName'] = 'posts';		
		
		$data['query'] .= " order by  `";
		
		$order = $this->input->get($data['tableName'].'_order'); 
		
		$data['query'] .= ($order ? $order:'date_modified')."` ";
		
		$sort = $this->input->get($data['tableName'].'_sort'); 
		
		$data['query'] .= ($sort ? $sort:'desc');
		
		$this->load->view('admin/v_header',$data);
		$this->load->view('admin/v_sidebar',$data);	
		$this->load->view('admin/posts/v_table',$data);
		$this->load->view('admin/v_footer',$data);		
	}

    public function Pre($data, $die = 1)
    {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
        if ($die) {
            die();
        }
    }

	
}