<?php defined('BASEPATH') OR exit('No direct script access allowed');

class M_question extends CI_Model {
	
	public $setting = NULL;
	public $data = NULL;
	
	function __construct()
	{
		parent::__construct();
		$this->setting = $this->settings->data;	
	}	
		
	public function unsubmit($id)
	{
		$data = array('published'=>0);
		return $this->db->where('id',$id)->update('questions',$data);
	}
		
	public function submit($id)
	{
		$data = array('published'=>1);
		return $this->db->where('id',$id)->update('questions',$data);
	}
			
	public function delete($id)
	{
        $childs =  $this->db->select('id')->where('parent',$id)->get('questions')->result();

        if(!empty($childs))
        {
            foreach ($childs as $item)
                $this->delete($item->id);
        }
		return $this->db->where('id',$id)->delete('questions');
	}

	
	/*====================================================
		CLIENT SIDE		
	====================================================*/	
	public function add($data)
	{
		if( ! isset($data['table'],$data['row_id'],$data['parent'],$data['text']) )
		return FALSE;
		
		if( ! isset( $this->user->data->id ) && ! isset($data['name'],$data['email']) )
		return FALSE;
		
		$userid = isset( $this->user->data->id ) ? $this->user->data->id:0;
		$name   = isset( $this->user->data->id ) ? $this->user->data->displayname:$data['name'];
        $email  = isset( $this->user->data->id ) ? $this->user->data->email:$data['email'];

		$data = array(
			'published'      => $data['published'],
			'user_id'   => $userid,
			'title'      => $data['title'],
			'regdate'      => date('Y-m-d H:i:s'),
		);
		
		if( $this->db->insert('questions',$data) )
		return $this->db->insert_id();
		return  NULL;
	}	 	 	 	 	 	 	 	 	 

    public function addQuestionPart($questionId,$data)
    {
		if((int)$data['master'] == 0 && $questionId == 0){
			return FALSE;
		}
		if(!intval($data['master'])){
			$data['qid'] = $questionId;
			$data['catid'] = 0;
		} else {
			$data['qid'] = 0;
		}
		$userid = isset( $this->user->data->id ) ? $this->user->data->id:0;
		$partData = array(
            'qid' 		=> $data['qid'] ,
            'catid' 		=> $data['catid'] ,
            'content' 	=> trim($data['content']) == '' ? NULL:$data['content'],
            'sound' 	=> trim($data['file']) == ''        ? NULL:$data['file'],
			'image' 	=> trim($data['image']) == ''        ? NULL:$data['image'],
			'user_id' 	=>$userid
        );

        if(isset($data['id']) && (int)$data['id'])
        {
            $this->db->where('id',(int)$data['id'])->update('questions',$partData);
			return $data['id'];
        }
        else
        {
            $this->db->insert('questions',$partData);
			return $this->db->insert_id();
        }
    }
	
	public function get($table,$row_id,$from=0,$limit=20)
	{
		$where = array('table'=>$table,'row_id'=>$row_id,'published'=>1);
		return $this->db->where($where)->get('questions',$from,$limit)->result();	
	}
	
	public function getPrimary($table,$row_id,$from=0,$total=20)
	{
		$where = array('table'=>$table,'row_id'=>$row_id,'parent'=>0,'published'=>1);
		return $this->db->where($where)->get('questions',$from,$total)->result();
		
	}	
	
	public function getChilds($table,$row_id,$id,$from=0,$total=20)
	{
		$where = array('table'=>$table,'row_id'=>$row_id,'parent'=>$id,'published'=>1);
		return $this->db->where($where)->get('questions',$from,$total)->result();
		
	}
	
	public function postQuestions($id,$from=0,$total=20)
	{
		$where = array('table'=>$table,'parent'=>$id,'published'=>1);
		return $this->db->where($where)->get('questions',$from,$total)->result();
		
	}	
	
	public function postQuestionsCount($id)
	{
		$where = array('table'=>'posts','row_id'=>$id,'published'=>1);
		return $this->db->where($where)->count_all_results('questions');	
		
	}
	
	public function selectById($id,$mod='post-questions')
	{
		if( ! $id ) return NULL;
		
		$id      = intval($id);
		$add     = $mod == 'user-reviews' ? "WHERE c.id=$id LIMIT 1":"WHERE id=$id LIMIT 1";
		$query   = $this->tools->buildQuery($mod,$add);
		$question = $this->db->query($query)->result();	
		
		return isset( $question[0] )	? $question[0]:NULL;
	}	
	
	public function htmlTemplate($cm)
	{
        $USER = isset( $this->user->data->id ) ? $this->user->data->id:0;

		$result =
            /** @lang text */
            '<div class="item question" item-id="'.$cm->id.'">
            <header>
                <div class="author">
                    '.($cm->user_username ?'<a href="'.site_url('user/'.$cm->user_username).'">':'').'
                    <img src="'.$this->user->getAvatarSrc(NULL,150,$cm->user_avatar).'" alt="'.html_escape($cm->name).'">
                    '.($cm->user_username ?'</a>':'').'
                </div>
                <div class="cm-date">
                    '.$this->tools->Date($cm->date).'
                </div>
            </header>
            <ul class="list-unstyled cm-options">
                <li class="toggle-rate '.( $cm->is_rated ? 'on':'' ).'" data-toggle=\'{"table":"questions","row":'.$cm->id.'}\'>
                    <i class="fa fa-star"></i>
                    <span>'.($cm->rate_count ? :'').'</span>
                </li>
                <li onClick="replyQuestion(this)">
                    <i class="fa fa-mail-reply-all"></i>
                </li>
                '.($USER && $cm->user_id == $USER ? '<li onClick="deleteQuestion(this)"><i class="fa fa-trash"></i></li>':'').'
            </ul>
            <div class="body clearfix">
                <div class="content wbr">'.html($cm->text).'</div>
                <div class="reply-con"></div>
            </div>
        </div>';
		return $result;	
	}
}
?>