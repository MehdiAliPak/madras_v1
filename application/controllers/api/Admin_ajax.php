<?php defined('BASEPATH') or exit('No direct script access allowed');

/*
		echo "<pre>";
		print_r();
		echo "</pre>";
		die;
*/

class Admin_ajax extends CI_Controller
{

    public $setting;

    function __construct()
    {

        parent::__construct();

        //if( ! $this->input->is_ajax_request() ) exit('No direct script access allowed 2');//Alaki

        $this->load->model('m_user', 'user');

        if (!$this->user->check_login() && $this->uri->segment(3) != 'login') {
            exit($this->tools->out('login'));
        }

        $this->setting = $this->settings->data;
    }

    public function login()
    {
        $done = 0;
        $msg = "";

        $data = $this->input->post(NULL, TRUE);
        if ($data) {
            if ($this->user->login($data))
                $done = 1;
            else
                $msg = "<span class=red>نام کاربری یا گذرواژه اشتباه است</span>";
        } else {
            $msg = "<span class=red>داده ای ارسال نشده</span>";
        }
        $data = array('done' => $done, 'msg' => $msg);
        echo $this->MakeJSON($data);
    }

    public function userinfo()
    {
        $data = array(
            'data' => $this->user->data,
            'can' => $this->user->can,
        );
        echo $this->MakeJSON($data);
    }

    public function media($type = 'images', $begin = 0, $total = 10)
    {
        $this->load->model('admin/m_media', 'media');

        $class = "";
        $attr = "";

        $include = $this->input->post('include');
        $exclude = $this->input->post('exclude');
        $selectable = $this->input->post('selectable');
        $multiple = $this->input->post('multiple');
        $options = $this->input->post('options');

        if (!$include && !$exclude) {
            $include = $type == 'images' ? array('jpg', 'jpe', 'jpeg', 'png', 'gif') : '';
            $exclude = $type == 'files' ? array('jpg', 'jpe', 'jpeg', 'png', 'gif') : '';
        }

        if ($selectable == 'true') $class .= " selectable ";
        if ($multiple == 'false') $attr .= ' unique-group="media-select" ';

        $options = $options == 'true' ? TRUE : FALSE;


        $dir = "uploads/";
        if ($user_dir = $this->input->post('dir')) {
            if ($this->user->is_admin() or $user_dir == $this->user->data->username)
                $dir .= $user_dir;
            else
                $dir .= $this->user->data->username;
        } else {
            $dir .= $this->user->data->username;
        }

        $this->media->scanDir($dir);
        $this->media->filter($include, $exclude);
        $this->media->Sort('time', 'desc');
        $this->media->setLimit($begin, $total);
        $this->media->addInfo();
        $data = $this->media->data['files'];

        if (empty($data)) return;

        foreach ($data as $key => $file) {
            echo $this->media->getTemplateFile($file, $type, $class, $attr, $options);
        }
    }

    public function mediadirlist()
    {
        $this->load->model('admin/m_media', 'media');

        $permission = TRUE;
        $dir = array();

        if ($this->user->is_admin()) {
            $dir = $this->media->scanPrimaryDir('uploads');
            $permission = TRUE;
        }
        $data = array('permission' => $permission, 'user' => $this->user->data->username, 'list' => $dir);
        echo $this->MakeJSON($data);
    }

    public function deletefile()
    {
        $done = FALSE;
        $msg = "";

        if ($this->user->can('delete_file')) {
            $this->load->model('admin/m_media', 'media');

            $file = $this->input->post('file');

            $dir = explode('/', $file);

            if (!$this->user->is_admin() && $dir[1] != $this->user->data->username) {
                $msg = "you are not allowed to delete this file !";
            } elseif ($this->media->deleteFile($file)) {
                $done = TRUE;
                $msg = "file deleted successfully";
            } else $msg = "can not delete file '$file' !";
        } else $msg = "you are not allowed to delete this file !";
        $data = array('done' => $done, 'file' => $file, 'msg' => $msg);
        echo $this->MakeJSON($data);
    }


    /***********************************
     * Books
     ***********************************/
    public function getCategoryBooks($value = NULL)
    {
        try {
            if (!strlen($value))
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_users')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $value = urldecode($value);
            $values = str_replace(",", "", $value);
            $this->db->select("p.id,p.title,c.name,m.meta_value price,c.id cid");
            $this->db->join('ci_category c', 'p.category=c.id', 'right', FALSE);
            $this->db->order_by('c.id,p.title', 'asc');
            $this->db->join('ci_post_meta m', 'm.post_id=p.id', 'inner', FALSE);
            $this->db->where("m.meta_key = 'price'");
            $this->db->where("m.meta_value != '0'");
            if (is_numeric($values))
                $this->db->where("c.id IN($value)");
            else
                $this->db->where("c.name LIKE '%$value%'");
            $result = $this->db->get('posts p')->result();

            $data = array();
            foreach ($result as $k => $v) {
                $data[] = array("label" => "($v->name) $v->title", "title" => "($v->name) $v->title", "idx" => $v->id, "price" => $v->price, "cid" => $v->cid);
            }
            $this->tools->outS(0, 'OK', array("result" => $data));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getDorehs($value = NULL)
    {
        try {
            if (!strlen($value))
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_discount')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $value = urldecode($value);
            $values = str_replace(",", "", $value);
            $this->db->select("d.id,t.name,d.tahsili_year,SUM(c.price) price");
            $this->db->join('ci_tecat t', 't.id=d.tecatid', 'inner', FALSE);
            $this->db->join('ci_dorehclass c', 'c.dorehid=d.id', 'inner', FALSE);
            $this->db->group_by('d.id,t.name,d.tahsili_year');
            if (is_numeric($values))
                $this->db->where("d.id IN($value)");
            else
                $this->db->where("t.name LIKE '%$value%'");
            $result = $this->db->get('doreh d')->result();

            $data = array();
            foreach ($result as $k => $v) {
                $data[] = array("label" => "$v->name (ID : $v->id)", "title" => "$v->name [ $v->tahsili_year - " . ($v->tahsili_year + 1) . "]" . ($v->price ? '' : ' [رایگان است]'), "idx" => $v->id, "price" => $v->price);
            }
            $this->tools->outS(0, 'OK', array("result" => $data));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
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

    public function getBooks($value = NULL)
    {
        try {
            if (!strlen($value)) {
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            }

            if (!$this->user->can('manage_discount')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }

            $value = urldecode($value);
            $values = str_replace(",", "", $value);
            $this->db->select("p.id,p.title,c.name,m.meta_value price");
            $this->db->join('ci_category c', 'p.category=c.id', 'left', FALSE);
            $this->db->order_by('p.title', 'asc');
            $this->db->join('ci_post_meta m', 'm.post_id=p.id', 'left', FALSE);
            $this->db->where("m.meta_key = 'price'");
            $this->db->where("m.meta_value != '0'");
            if (is_numeric($values))
                $this->db->where("p.id IN($value)");
            else
                $this->db->where("p.title LIKE '%$value%'");
            $result = $this->db->get('posts p')->result();
            $data = array();
            foreach ($result as $k => $v) {
                $data[] = array("label" => "($v->name) $v->title (ID : $v->id)", "title" => "($v->name) $v->title", "idx" => $v->id, "price" => $v->price);
            }
            $this->tools->outS(0, 'OK', array("result" => $data));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getStudents($value = NULL)
    {
        try {
            if (!strlen($value)) {
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            }

            if (!$this->user->can('manage_discount')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }

            $value = urldecode($value);
            $values = str_replace(",", "", $value);
            $this->db->select("u.id,u.displayname,u.username");
            $this->db->order_by('u.displayname,u.username', 'asc');
            if (is_numeric($values)) {
                $this->db->where("u.id IN($value)");
            } else {
                $this->db->where("u.displayname LIKE '%$value%' OR u.username LIKE '%$value%'");
            }
            $result = $this->db->get('users u')->result();
            $data = array();
            foreach ($result as $k => $v) {
                $displayname = "($v->username) $v->displayname (ID : $v->id)";
                $data[] = array("label" => $displayname, "idx" => $v->id);
            }
            $this->tools->outS(0, 'OK', array("result" => $data));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getSectionData()
    {
        try {
            $data = $this->input->post();
            $value = @$data["value"];
            $section = @$data["section"];
            if (!strlen($value) || !strlen($section)) {
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            }

            if (!$this->user->can('manage_discount')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }

            $value = urldecode($value);
            $values = str_replace(",", "", $value);
            if($section == "membership"){
                $section = "category";
            }
            switch ($section) {
                case "tecat":
                case "category":
                    $select = "id AS value,name AS text";
                    $order_by = "name";
                    $where = "name LIKE '%$value%'";
                    break;
                case "classroom":
                case "supplier":
                case "post":
                case "classonline":
                    $select = "id AS value,title AS text";
                    $order_by = "title";
                    $where = "title LIKE '%$value%'";
                    break;
                default:
                    throw new Exception('اطلاعاتی ارسال نشده', 1);
            }
            $this->db->select($select);
            $this->db->order_by($order_by, 'asc');
            if (is_numeric($values)) {
                $this->db->where("id IN($value)");
            } else {
                $this->db->where($where);
            }
            $result = $this->db->get($section)->result();
            $data = array();
            foreach ($result as $k => $v) {
                $text = "$v->text (ID : $v->value)";
                $data[] = array("label" => $text, "idx" => $v->value);
            }
            $this->tools->outS(0, 'OK', array("result" => $data));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    /***********************************
     * User Books
     ***********************************/
    public function deleteUserBooks($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_users')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            $this->db->where('id', $id)->delete('user_books');
            $this->tools->outS(0, 'OK');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function addUserBooks()
    {
        try {
            $data = $this->input->post();
            $bid = isset($data['bid']) ? $data['bid'] : 0;
            $uid = isset($data['uid']) ? $data['uid'] : 0;
            if (!$bid || !$uid)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_users'))
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            $adminid = $this->user->user_id;
            $this->db->select("id");
            $this->db->where("`user_id` = $uid");
            $this->db->where("`price` = 0");
            $this->db->where("`ref_id` = '$adminid'");
            $result = $this->db->get('factors')->result();
            $factor_id = 0;
            if (count($result)) {
                $factor_id = $result[0]->id;
            }

            $this->db->select("id");
            $this->db->where("`user_id` = $uid");
            $this->db->where("`book_id` = $bid");
            $result = $this->db->get('user_books')->result();
            if (!count($result)) {
                $data = array("user_id" => $uid, "state" => "ثبت شده توسط " . $this->user->data->username, "ref_id" => $adminid, "price" => 0, "section" => 'book', "cprice" => 0, "cdate" => time(), "status" => 0);
                $this->db->insert('factors', $data);
                $factor_id = $this->db->insert_id();
                $data = array("user_id" => $uid, "book_id" => $bid, "factor_id" => $factor_id);
                $this->db->insert('user_books', $data);
            }
            $this->db->select('ub.id ubid,b.id,b.title,c.name AS cname,d.name AS sath');
            $this->db->join('ci_posts b', 'ub.book_id=b.id', 'right', FALSE);
            $this->db->join('ci_category c', 'b.category=c.id', 'right', FALSE);
            $this->db->join('ci_category d', 'c.parent=d.id', 'right', FALSE);
            $this->db->where('ub.user_id', $uid);
            $books = $this->db->get('user_books ub')->result();

            $this->tools->outS(0, 'OK', array("books" => $books));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getBookTest($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            $this->db->select('t.id,t.testnumber,t.category,t.question,t.true_answer,t.answer_1,t.answer_2,t.answer_3,t.answer_4,t.page,t.term');
            $test = $this->db->where('book_id', (int)$id)->order_by('category', 'asc')->order_by('id', 'asc')->get('tests t')->result();
            $this->tools->outS(0, 'OK', array('test' => $test));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function saveBookTest()
    {
        try {
            if (!$this->user->can('edit_post'))
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            $data = $this->input->post();
            if (!isset($data["bookid"]))
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            $tests = (array)@$data["book"]["test"];
            $id = $data["bookid"];
            $deletedTests = @$data["book"]["deleted_test"];
            $message = array();
            if (is_array($deletedTests)) {
                foreach ($deletedTests as $testId)
                    $this->db->where('id', $testId)->delete('tests');
                $message[] = sprintf("تعداد %s سوال تستی حذف گردید", count($deletedTests));
                $data = array('date_modified' => date("Y-m-d H:i:s"));
                $this->db->where('id', $id)->update('posts', $data);
            }

            $count = count($tests);
            if ($count) {
                $this->load->model('m_book', 'book');
                foreach ($tests as $test)
                    $this->book->addBookTest($id, $test);
                $message[] = sprintf("تعداد %s سوال تستی ثبت / بروزرسانی گردید", $count);
                $data = array('date_modified' => date("Y-m-d H:i:s"), "has_test" => $count);
                $this->db->where('id', $id)->update('posts', $data);
            } else {
                $data = array("has_test" => $count);
                $this->db->where('id', $id)->update('posts', $data);
            }
            $ubData = array(
                'need_update' => 1,
            );
            $this->db->where('book_id', $id)->update('user_books', $ubData);
            $this->tools->outS(0, implode(".", $message));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function deleteBookTest()
    {
        try {
            if (!$this->user->can('edit_post'))
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            $data = $this->input->post();
            if (!isset($data["bookid"]))
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            $id = $data["bookid"];
            $message = array();
            $this->db->where('book_id', $id)->delete('tests');
            $message[] = "تمامی سوال های تستی کتاب انتخابی حذف گردید";
            $ubData = array(
                'need_update' => 1,
            );
            $this->db->where('book_id', $id)->update('user_books', $ubData);
            $this->tools->outS(0, implode(".", $message));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getBookTashrihi($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            $this->db->select('t.id,t.testnumber,t.category,t.question,t.answer,t.page,t.term,t.barom');
            $tashrihi = $this->db->where('book_id', (int)$id)->order_by('category', 'asc')->order_by('id', 'asc')->get('tashrihi t')->result();
            $this->tools->outS(0, 'OK', array('tashrihi' => $tashrihi));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getBookMembership($id = NULL)
    {
        try {
            if (!$id) {
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            }
            $this->db->select('b.id,u.tel,ub.expiremembership,IF(ub.expiremembership="0000-00-00","",pdate(ub.expiremembership)) AS expdate,IF(ub.expiremembership < CURDATE(),0,1) AS state,u.displayname');
            $this->db->join('ci_posts b', 'ub.book_id=b.id', 'inner', FALSE);
            $this->db->join('ci_users u', 'ub.user_id=u.id', 'inner', FALSE);
            $this->db->where('b.id', $id);
            $this->db->where('ub.expiremembership <> "0000-00-00"');
            $books = $this->db->get('user_books ub')->result();
            $this->tools->outS(0, 'OK', array('result' => $books));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getDataMembership($id = NULL)
    {
        try {
            if (!$id) {
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            }

            $O = $this->db->select('*')->get('membership')->result();
            $membership = [];
            foreach ($O as $value) {
                $membership[$value->id] = $value;
            }

            $O = $this->db->select('*')->where('user_id', $id)->get('factors')->result();
            $factors = [];
            foreach ($O as $value) {
                $factors[$value->id] = $value;
            }

            $O = $this->db->select('*')->where('parent', 0)->get('category')->result();
            $category = [];
            foreach ($O as $value) {
                $category[$value->id] = $value->name;
            }
            $this->db->select('*');
            $this->db->where('user_id', $id);
            $O = $this->db->get('user_membership')->result();
            $result = [];
            foreach ($O as $value) {
                $expdate = explode("-", $value->startdate);//Alireza Balvardi
                $value->startdate = gregorian_to_jalali($expdate[0], $expdate[1], $expdate[2], "/");

                $expdate = explode("-", $value->enddate);//Alireza Balvardi
                $value->enddate = gregorian_to_jalali($expdate[0], $expdate[1], $expdate[2], "/");
                $dest = $this->EnCode('user_membership');
                $destid = $this->EnCode($value->id);
                $result[] = ['عضویت', $membership[$value->membership_id]->title, $membership[$value->membership_id]->allowmonths, $value->startdate, $value->enddate, @$factors[$value->factor_id]->state, $dest, $destid];
            }

            $this->db->select('*');
            $this->db->where('user_id', $id);
            $O = $this->db->get('user_catmembership')->result();
            foreach ($O as $value) {
                $expdate = explode("-", $value->startdate);//Alireza Balvardi
                $value->startdate = gregorian_to_jalali($expdate[0], $expdate[1], $expdate[2], "/");

                $expdate = explode("-", $value->enddate);//Alireza Balvardi
                $value->enddate = gregorian_to_jalali($expdate[0], $expdate[1], $expdate[2], "/");
                $dest = $this->EnCode('user_catmembership');
                $destid = $this->EnCode($value->id);
                $result[] = ['دسته بندی', $category[$value->cat_id], $value->membership_id, $value->startdate, $value->enddate, @$factors[$value->factor_id]->state, $dest, $destid];
            }
            $user = $this->db->where('id', $id)->get('users')->row();
            $user->displayname = $user->displayname ? $user->displayname . " [$user->username]" : $user->username;
            $this->tools->outS(0, 'OK', array('result' => $result, 'user' => $user));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getAllMembership($id = NULL)
    {
        try {
            if (!$id) {
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            }


            $this->db->select('*');
            $this->db->where('user_id', $id);
            $this->db->where('enddate > CURDATE()');
            $O = $this->db->get('user_membership')->result();
            $ids = [0];
            foreach ($O as $value) {
                $ids[] = $value->membership_id;
            }

            $O = $this->db->select('*')->where_not_in('id', $ids)->get('membership')->result();
            $result = [];
            $i = 0;

            foreach ($O as $value) {
                $result[$i++] = ["membership", $value];
            }

            $this->db->select('*');
            $this->db->where('user_id', $id);
            $this->db->where('enddate > CURDATE()');
            $O = $this->db->get('user_catmembership')->result();
            $ids = [0];
            foreach ($O as $value) {
                $ids[] = $value->cat_id;
            }
            $O = $this->db->select('*')->where_not_in('id', $ids)->where('parent', 0)->get('category')->result();
            foreach ($O as $value) {
                $result[$i++] = ["category", $value];
            }
            $this->tools->outS(0, 'OK', array('result' => $result));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function saveAdminMembership($id = NULL)
    {
        try {
            $user = $this->session->userdata('ci_user');
            if (!$id) {
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            }
            list($user_id, $section, $sectionid, $plan_id) = $this->input->post("src");
            $out = ['message' => 'ثبت با موفقیت انجام شد'];
            $cf = null;
            switch ($section) {
                case "membership":
                    $this->load->model('m_membership', 'membership');
                    $destFactor = $this->membership;
                    $exists = $destFactor->isBought($user_id, $sectionid);
                    if (!$exists) {
                        $cf = $destFactor->createFactor($user_id, $sectionid);
                    }
                    break;
                case "category":
                    $this->load->model('m_category', 'category');
                    $destFactor = $this->category;
                    $exists = $destFactor->isBought($user_id, $sectionid, $plan_id);
                    if (!$exists) {
                        $cf = $destFactor->createFactor($user_id, $sectionid, $plan_id);
                    }
                    break;
            }
            if ($cf) {
                $factor = $cf["factor"];
                $data = ['paid' => 0, 'status' => 0, 'cprice' => 0, 'price' => 0, 'owner' => $user['user_id'], 'pdate' => time(), 'state' => 'توسط مدیر' . ' [' . $user["username"] . ']'];
                $destFactor->updatetFactor($factor->id, $data);
            } else {
                $out = ['message' => "اشتراک قبلا خریداری شده است"];
            }

            $this->tools->outS(0, 'OK', $out);
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function deleteAdminMembership($id = NULL)
    {
        try {
            list($dest, $id) = $this->input->post("src");
            $out = ['msg' => 'حذف با موفقیت انجام شد'];
            $id = $this->DeCode($id);
            $dest = $this->DeCode($dest);
            switch ($dest) {
                case "user_membership":
                    $this->db->where('id', $id)->delete($dest);
                    break;
                case "user_catmembership":
                    $result = $this->db->where('id', $id)->get($dest)->row();
                    if(isset($result->cat_id)){
                        $user_id = $result->user_id;
                        $factor_id = $result->factor_id;
                        $this->db
                            ->where('user_id', $user_id)
                            ->where('factor_id', $factor_id)
                            ->delete("user_books");
                    }
                    $this->db->where('id', $id)->delete($dest);
                    break;
                default:
                    $out = ['done' => false, 'msg' => 'اطلاعات ارسالی صحیح نیست'];
            }

            $this->tools->outS(0, 'OK', $out);
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function saveBookTashrihi()
    {
        try {
            if (!$this->user->can('edit_post'))
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            $data = $this->input->post();
            if (!isset($data["bookid"]))
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            $tashrihis = (array)@$data["book"]["tashrihi"];
            $id = $data["bookid"];
            $deletedTashrihis = @$data["book"]["deleted_tashrihi"];
            $message = array();
            if (is_array($deletedTashrihis)) {
                foreach ($deletedTashrihis as $tashrihiId)
                    $this->db->where('id', $tashrihiId)->delete('tashrihi');
                $message[] = sprintf("تعداد %s سوال تشریحی حذف گردید", count($deletedTashrihis));
            }
            $count = count($tashrihis);
            if ($count) {
                $this->load->model('m_book', 'book');
                foreach ($tashrihis as $tashrihi)
                    $this->book->addBookTashrihi($id, $tashrihi);
                $message[] = sprintf("تعداد %s سوال تشریحی ثبت / بروزرسانی گردید", $count);
                $data = array('date_modified' => date("Y-m-d H:i:s"), "has_tashrihi" => $count);
                $this->db->where('id', $id)->update('posts', $data);
            } else {
                $data = array("has_tashrihi" => $count);
                $this->db->where('id', $id)->update('posts', $data);
            }
            $ubData = array(
                'need_update' => 1,
            );
            $this->db->where('book_id', $id)->update('user_books', $ubData);
            $this->tools->outS(0, implode(".", $message));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function deleteBookTashrihi()
    {
        try {
            if (!$this->user->can('edit_post'))
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            $data = $this->input->post();
            if (!isset($data["bookid"]))
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            $id = $data["bookid"];
            $deletedTashrihis = @$data["book"]["deleted_tashrihi"];
            $message = array();
            $this->db->where('book_id', $id)->delete('tashrihi');
            $message[] = "تمامی سوال های تشریحی کتاب انتخابی حذف گردید";
            $ubData = array(
                'need_update' => 1,
            );
            $this->db->where('book_id', $id)->update('user_books', $ubData);
            $this->tools->outS(0, implode(".", $message));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getUserBooks($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_users')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('users'))
                throw new Exception('این کاربر حذف شده است', 2);

            $user = $this->db//->select('id,username,name,family,displayname,email,avatar,cover,type,active,level,approved,last_seen,date')
            ->where('id', $id)->get('users')->row();

            if ($user->cover == '')
                $user->cover = $this->setting['default_user_cover'];

            if ($user->avatar == '')
                $user->avatar = $this->setting['default_user_avatar'];

            $this->db->select('ub.id ubid,b.id,b.title,c.name AS cname,d.name AS sath');
            $this->db->join('ci_posts b', 'ub.book_id=b.id', 'right', FALSE);
            $this->db->join('ci_category c', 'b.category=c.id', 'right', FALSE);
            $this->db->join('ci_category d', 'c.parent=d.id', 'right', FALSE);
            $this->db->join('ci_factors f', '(ub.factor_id=f.id AND f.status IN(0,2))', 'right', FALSE);
            $this->db->where('ub.user_id', $id);
            //$this->db->group_by('b.id');
            $books = $this->db->get('user_books ub')->result();

            $this->tools->outS(0, 'OK', array('user' => $user, 'books' => $books));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getUserHL($user_id = NULL)
    {
        $user_id = (int)$user_id;
        try {
            if (!$user_id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_users')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $this->db->select("h.id      as highlight_id");
            $this->db->select("h.part_id as text_id");
            $this->db->select("h.title   as highlight_title");
            $this->db->select("h.text    as highlight_text");
            $this->db->select("h.description   as highlight_description");
            $this->db->select("h.color   as highlight_color");
            $this->db->select("h.start   as highlight_start");
            $this->db->select("h.end     as highlight_end");
            $this->db->select("h.sharh");
            $this->db->select("(SELECT p.title FROM ci_book_meta AS m INNER JOIN ci_posts AS p ON p.id=m.book_id WHERE m.id=h.part_id) AS bookname");
            $this->db->where('h.user_id', $user_id);
            $result = $this->db->get('highlights h')->result();
            foreach ($result as $k => $v) {
                $tags = $this->getHighlightTag($v->highlight_id);
                $result[$k]->tag = $tags;
            }

            $user = $this->db->where('id', $user_id)->get('users')->row();

            $this->tools->outS(0, 'OK', array('user' => $user, 'result' => $result));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function getHighlightTag($highlight_id)
    {
        $this->db->select("h.id      as hightag_id");
        $this->db->select("h.title   as hightag_title");
        $this->db->select("h.public");
        $this->db->where('h.hid', $highlight_id);

        return $this->db->get('hightag h')->result();
    }

    public function getUserMobiles($user_id = NULL)
    {
        $user_id = (int)$user_id;
        try {
            if (!$user_id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_users')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $this->db->select("m.*");
            $this->db->where('m.user_id', $user_id);
            $result = $this->db->get('user_mobile m')->result();
            $user = $this->db->where('id', $user_id)->get('users')->row();
            $this->tools->outS(0, 'OK', array('user' => $user, 'result' => $result));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    public function DeleteUserMobile($id = NULL)
    {
        $id = (int)$id;
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_users')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $this->db->where('id', $id)->delete('user_mobile');
            $this->tools->outS(0, 'OK');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    /***********************************
     * Users
     ***********************************/
    public function getUserInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_users')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('users'))
                throw new Exception('این کاربر حذف شده است', 2);

            $user = $this->db//->select('id,username,name,family,displayname,email,avatar,cover,type,active,level,approved,last_seen,date')
            ->where('id', $id)->get('users')->row();

            if ($user->cover == '')
                $user->cover = $this->setting['default_user_cover'];

            if ($user->avatar == '')
                $user->avatar = $this->setting['default_user_avatar'];

            $this->tools->outS(0, 'OK', array('user' => $user));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    //Alireza Balvardi Start
    public function addUser()
    {

        try {
            if (!$this->user->can('edit_user'))
                throw new Exception('شما به این بخش دسترسی ندارید', 2);
            $data = $this->input->post();
            $data['username'] = strtolower($data['username']);
            $data['email'] = strtolower($data['email']);
            $username = $data['username'];
            $email = $data['email'];

            $user = $this->db->where('username', $username)->get('users')->row();
            if (is_object($user) && isset($user->id))
                throw new Exception('نام کاربری ' . $username . ' قبلا استفاده شده است', 2);

            $user = $this->db->where('email', $email)->get('users')->row();
            if (is_object($user) && isset($user->id))
                throw new Exception('ایمیل ' . $email . ' قبلا استفاده شده است', 2);

            $this->load->library('form_validation');

            $this->form_validation->set_rules('username', 'نام کاربری', 'trim|xss_clean|required|alpha_numeric|is_unique[users.username]|min_length[4]|max_length[30]');
            $this->form_validation->set_rules('email', 'ایمیل', 'trim|xss_clean|valid_email');
            //$this->form_validation->set_rules('name', 'نام', 'trim|xss_clean|max_length[50]');
            //$this->form_validation->set_rules('family', 'نام خانوادگی', 'trim|xss_clean|max_length[50]');
            $this->form_validation->set_rules('tel', 'موبایل', 'trim|xss_clean|is_unique[users.tel]');
            $this->form_validation->set_rules('displayname', 'نام نمایشی', 'trim|xss_clean|max_length[50]');
            $this->form_validation->set_rules('password', 'رمز', 'trim|xss_clean');

            if ($this->form_validation->run() == FALSE)
                throw new Exception(validation_errors(), 2);


            $allowed = array(
                //'name','family',
                'email', 'displayname', 'cover', 'avatar',
                'pending_reason', 'approved', 'password',
                'gender', 'age', 'tel', 'national_code', 'birthday',
                'city', 'state', 'country', 'postal_code', 'address', 'username'
            );


            if ($this->user->can('edit_user_role'))
                $allowed[] = 'level';

            $data = array_intersect_key($data, array_flip($allowed));

            if (trim($data['displayname']) == "")
                $data['displayname'] = $username;

            if (trim($data['password']) != "")
                $data['password'] = do_hash($data['password']);
            else
                unset($data['password']);

            if (isset($data['cover']) && $data['cover'] == $this->setting['default_user_cover'])
                $data['cover'] = NULL;

            if (isset($data['avatar']) && $data['avatar'] == $this->setting['default_user_avatar'])
                $data['avatar'] = NULL;

            if (!$this->db->insert('users', $data))
                throw new Exception("خطا در انجام عملیات", 1);

            if (isset($data['active'])) {
                if ($data['active'] == 1) {
                    $url = site_url('user') . "/" . $username;
                    $mail = "همراه گرامی، پروفایل شما توسط تیم پشتیبانی تولید شد! <br>";
                    $mail .= "لینک پروفایل شما در ابزاربر : <a href=\"{$url}\">{$username}</a>";
                    $this->tools->sendEmail($email, "پروفایل شما تایید شد!", $mail);
                }
            }

            $this->tools->outS(0, "اطلاعات کاربری جدید ذخیره شد !");
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    //Alireza Balvardi End
    public function updateUser($id = NULL)
    {

        try {
            $id = intval($id);
            if (!$id or !$this->db->where('id', $id)->count_all_results('users'))
                throw new Exception('کاربری با این شماره پیدا نشد', 1);

            if (!$this->user->can('edit_user'))
                throw new Exception('شما به این بخش دسترسی ندارید', 2);

            $user = $this->db->where('id', $id)->get('users')->row();

            if ($user->level == 'admin' && $this->user->data->level != 'admin')
                throw new Exception('شما نمی توانید اطلاعات مدیر را ویرایش کنید', 1);

            $this->load->library('form_validation');

            if ($user->email != $this->input->post('email'))
                $this->form_validation->set_rules('email', 'ایمیل', 'trim|xss_clean|valid_email|is_unique[users.email,users.id.' . $id . ']');

            $this->form_validation->set_rules('name', 'نام', 'trim|xss_clean|max_length[50]');
            $this->form_validation->set_rules('family', 'نام خانوادگی', 'trim|xss_clean|max_length[50]');
            $this->form_validation->set_rules('displayname', 'نام نمایشی', 'trim|xss_clean|max_length[50]');
            $this->form_validation->set_rules('password', 'رمز', 'trim|xss_clean');

            if ($this->form_validation->run() == FALSE)
                throw new Exception(validation_errors(), 2);

            $data = $this->input->post();

            $data['username'] = strtolower($data['username']);
            $data['email'] = strtolower($data['email']);

            $allowed = array(
                'name', 'family', 'email', 'displayname', 'cover', 'avatar',
                'pending_reason', 'approved', 'password',
                'gender', 'support', 'age', 'tel', 'national_code', 'birthday',
                'city', 'state', 'country', 'postal_code', 'address',
            );

            if ($this->user->can('edit_user_role') /*&& $user->level != 'admin' && $data['level'] != 'admin' */)
                $allowed[] = 'level';

            if ($user->level != 'admin')
                $allowed[] = 'active';

            $data = array_intersect_key($data, array_flip($allowed));

            if (trim($data['displayname']) == "")
                $data['displayname'] = $user->username;

            if (trim($data['password']) != "")
                $data['password'] = do_hash($data['password']);
            else
                unset($data['password']);

            if (isset($data['cover']) && $data['cover'] == $this->setting['default_user_cover'])
                $data['cover'] = NULL;

            if (isset($data['avatar']) && $data['avatar'] == $this->setting['default_user_avatar'])
                $data['avatar'] = NULL;

            if (!$this->db->where('id', $id)->update('users', $data))
                throw new Exception("خطا در انجام عملیات", 1);

            if (isset($data['active'])) {
                if ($user->active == 0 && $data['active'] == 1) {
                    $url = site_url('user') . "/" . $user->username;
                    $mail = "همراه گرامی، پروفایل شما توسط تیم پشتیبانی مورد تایید قرار گرفت! <br>";
                    $mail .= "لینک پروفایل شما در ابزاربر : <a href=\"{$url}\">{$user->username}</a>";
                    $this->tools->sendEmail($user->email, "پروفایل شما تایید شد!", $mail);
                }
                if ($user->active == 1 && $data['active'] == 0) {
                    $mail = "همراه گرامی، متاسفانه پروفایل شما مسدود شد <br>";
                    $mail .= "دلیل مسدود شدن حساب کاربری : " . $data['pending_reason'];
                    $this->tools->sendEmail($user->email, "پروفایل شما مسدود شد!", $mail);
                }
            }

            $this->tools->outS(0, "اطلاعات به روز شد !");
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function adduserlevel($id)
    {
        $done = FALSE;
        $data = $this->input->post('level');

        if (isset($id) && !empty($data)) {
            //$data['id'] = (int)$id;
            if ($this->user->addLevel($id, $data))
                $done = TRUE;
        }
        $data = array('done' => $done);
        echo $this->MakeJSON($data);
    }

    public function updateuserlevel($id)
    {
        $done = FALSE;
        $data = $this->input->post('level');
        if ($this->user->updateLevel($id, $data))
            $done = TRUE;
        $data = array('done' => $done);
        echo $this->MakeJSON($data);
    }

    public function deleteuserlevel($id, $replace)
    {
        $done = FALSE;

        if ($this->user->deleteLevel($id, $replace))
            $done = TRUE;
        $data = array('done' => $done);
        echo $this->MakeJSON($data);
    }

    /***********************************
     * Posts
     ***********************************/
    public function post($op = "save", $action = "publish")
    {


        global $POST_TYPES;

        /*$post = $this->input->post('book[test]');

        echo '<pre style="direction:ltr;">';

        print_r($post);

        echo '</pre>';

        return;*/

        switch ($op) {
            case 'save':

                $id = $this->input->post('id');
                $d_data = array(
                    'title' => '',
                    'content' => '',
                    'excerpt' => '',
                    'category' => '',
                    'tags' => '',
                    'thumb' => '',
                    'author' => $this->user->user_id,
                    'date_modified' => '',
                    'date' => ''
                );
                //================= base data ===================//
                $data = $this->input->post('data');
                $tags = $this->input->post('tags', TRUE);
                $dt = $this->input->post('date');
                $md = $this->input->post('mdate');
                $category = $this->input->post('category');
                $type = $this->input->post('data[type]');
                $postType = @$POST_TYPES[$type];
                $meta = NULL;
                $nashr = NULL;

                if (isset($data['title']))
                    $data['title'] = $this->security->fixString($data['title']);

                if (isset($data['content']))
                    $data['content'] = $this->security->fixString($data['content']);

                if (isset($data['excerpt']))
                    $data['excerpt'] = $this->security->fixString($data['excerpt']);

                if (isset($data['meta_keywords']))
                    $data['meta_keywords'] = $this->security->fixString($data['meta_keywords']);

                if (isset($data['meta_description']))
                    $data['meta_description'] = $this->security->fixString($data['meta_description']);

                if ($tags) {
                    usort($tags, function ($a, $b) {
                        return mb_strlen($a) > mb_strlen($b);
                    });
                    $data['tags'] = implode('+', $tags);
                }
                if ($category) $data['category'] = implode(',', $category);

                $dt = jalaliToGregorian($dt['y'], $dt['m'], $dt['d'], '-') . ' ' . $dt['H'] . ':' . $dt['i'] . ':00';
                $md = jalaliToGregorian($md['y'], $md['m'], $md['d'], '-') . ' ' . $md['H'] . ':' . $md['i'] . ':00';

                $data['date'] = $dt;
                $data['date_modified'] = $md;

                $data = array_merge($d_data, $data);

                //================= download box ===================//
                if (isset($postType['support']) && in_array('dl_box', $postType['support'])) {
                    $metaDl = $this->input->post('meta[dl]');
                    $_metaDl = NULL;

                    if ($metaDl)
                        for ($i = 0; $i < count($metaDl['file']); $i++)
                            $_metaDl[$i] = array('name' => $metaDl['name'][$i], 'file' => $metaDl['file'][$i]);

                    $meta['dl_box'] = $_metaDl;
                }

                if ($type == 'book') {
                    $metaDl2 = $this->input->post('meta[dlbook]');
                    $_metaDl2 = NULL;

                    if ($metaDl2)
                        for ($i = 0; $i < count($metaDl2['file']); $i++)
                            $_metaDl2[$i] = array('name' => $metaDl2['name'][$i], 'file' => $metaDl2['file'][$i]);

                    $meta['dl_book'] = $_metaDl2;
                }

                //=============== gallery =============//
                if (isset($postType['support']) && in_array('gallery', $postType['support']))
                    $meta['post_thumb'] = $this->input->post('meta[thumb]');

                //=============== meta product =============//
                if ($type == "product")
                    $meta['product_data'] = $this->input->post('meta[product]');

                //================= additional meta ===================//
                if (isset($postType['meta']) && is_array($postType['meta'])) {
                    foreach ($postType['meta'] as $mik => $mi) {
                        // $this->form_validation->set_rules('meta['.$mik.']', $mi['name'], $mi['validation']);
                        $meta[$mik] = $this->input->post('meta[' . $mik . ']');
                        if ($mik == 'price') {
                            $Xdata = array("price" => $meta[$mik]);
                            $this->db->where('id', $id)->update('posts', $Xdata);
                        }
                    }
                }
                //================= additional nashr ===================//
                if (isset($postType['nashr']) && is_array($postType['nashr'])) {
                    foreach ($postType['nashr'] as $mik => $mi) {
                        // $this->form_validation->set_rules('nashr['.$mik.']', $mi['name'], $mi['validation']);
                        $nashr[$mik] = $this->input->post('nashr[' . $mik . ']');
                        if (is_array($nashr[$mik])) {
                            $nashr[$mik] = implode(",", $nashr[$mik]);
                        }
                    }
                }

                //================= book data ===================//
                if ($type == 'book') {
                    $this->load->model('m_book', 'book');
                    //========== parts =========//
                    $deletedParts = $this->input->post('deleted');
                    if (is_array($deletedParts)) {
                        foreach ($deletedParts as $partId) {
                            $this->db->where('id', $partId)->delete('book_meta');
                        }
                    }
                }
                if (isset($meta["allowmembership"]) && $meta["allowmembership"]) {
                    $data["has_membership"] = 1;
                }
                //=============== save =============//
                $this->post->savePost($id, $data, $meta, $action, $nashr);
                if ($type == 'book') {
                    echo '<script>setTimeout(function(){location.href="' . (site_url('admin/book/primary')) . '"},700)</script>';
                }

                break;
            case 'publish':
            case 'draft':
            case 'trash':
            case 'delete':
            case 'test'://Alireza Balvardi
                $this->_pdtd($op, $action);
                break;
        }
    }

    private function _pdtd($op = NULL, $id = NULL)
    {

        $this->load->model('admin/m_post', 'post');

        $done = FALSE;
        $msg = "انجام نشد";
        $return = array();

        if (!$id or !$op) {
            $data = array('done' => FALSE, 'msg' => 'no data', 'data' => '');
            echo $this->MakeJSON($data);
            return NULL;
        }

        $post_type = $this->db->get_field('type', 'posts', $id);

        switch ($op) {
            case 'publish':
                if ($this->user->can('submit_' . $post_type) && $this->post->publish($id)) {
                    $done = true;
                    $msg = "منتشر شد";
                }
                break;
            case 'draft':
                if ($this->user->can('submit_' . $post_type) && $this->post->toDruft($id)) {
                    $done = true;
                    $msg = "به پیش نویس انتقال یافت";
                }
                break;/**/
            case 'test'://Alireza Balvardi
                if ($this->user->can('submit_publish') && $this->post->test($id)) {
                    $done = true;
                    $msg = "آماده انتشار شد";
                }
                break;//Alireza Balvardi
            case 'trash':
                if ($this->user->can('suspend_' . $post_type) && $this->post->toTrashs($id)) {
                    $done = true;
                    $msg = "به زباله دان انتقال یافت";
                }
                break;
            case 'delete':
                if ($this->user->can('delete_' . $post_type) && $this->post->delete($id)) {
                    $done = true;
                    $msg = "حذف شد";
                }
                break;
        }

        $this->db->where(array('type' => $post_type, 'published' => 1, 'draft' => NULL));
        $all_posts = $this->db->count_all_results('posts');

        $this->db->where(array('type' => $post_type, 'published' => 0, 'draft IS NOT NULL' => NULL));//Alireza Balvardi
        $all_drafts = $this->db->count_all_results('posts');


        $this->db->where(array('type' => $post_type, 'published' => 0, 'draft' => NULL));
        $all_trashs = $this->db->count_all_results('posts');

        $this->db->where(array('type' => $post_type, 'published' => 2));//Alireza Balvardi
        $all_tests = $this->db->count_all_results('posts');
        $return = array(
            'primary' => $all_posts,
            'draft' => $all_drafts,
            'recyclebin' => $all_trashs,
            'test' => $all_tests//Alireza Balvardi
        );
        $data = array('done' => $done, 'msg' => $msg, 'data' => $return);
        echo $this->MakeJSON($data);

    }

    public function save_part($book_id = NULL)
    {
        $result = ['done' => TRUE];

        $this->load->model('m_book', 'book');
        $ubData = array(
            'need_update' => 1,
        );
        $this->db->where('book_id', $book_id)->update('user_books', $ubData);

        $id = $this->book->addBookPart((int)$book_id, $this->input->post());

        if (is_numeric($id)) $result['part']['id'] = $id;

        echo $this->MakeJSON($result);
    }

    public function save_questionpart($question_id = NULL)
    {
        $result = ['done' => TRUE];
        $post = $this->input->post();

        $this->load->model('m_question', 'question');

        $id = $this->question->addQuestionPart((int)$question_id, $post);

        if (is_numeric($id) && $id) {
            $result['part']['id'] = $id;
            $result['part']['master'] = $post['master'];
            $result['part']['qid'] = $post['qid'];
        } else {
            $result = ['done' => FALSE, 'message' => "لطفا ابتدا پشتیبانی را ذخیره نمایید و سپس پاسخ آن را ذخیره نمایید"];
        }
        echo $this->MakeJSON($result);
    }

    public function delete_questionpart($id = NULL)
    {
        $this->db->where('id', (int)$id)->delete('questions');
    }

    public function delete_part($id = NULL)
    {
        $this->db->where('id', (int)$id)->delete('book_meta');
    }

    public function save_pages($id = NULL)
    {
        $pages = $this->input->post('pages');
        $result = [
            'done' => $this->post->updatePostMeta((int)$id, 'pages', $pages)
        ];

        $pages = explode(',', $pages);
        $count = count($pages);
        $Xdata = array("pages" => $count);
        $this->db->where('id', $id)->update('posts', $Xdata);

        $ubData = array(
            'need_update' => 1,
        );
        $this->db->where('book_id', $id)->update('user_books', $ubData);


        $rows = $this->db->select('id,order,index,page,paragraph')->order_by('order')->where('book_id', $id)->get('book_meta')->result();

        $pc = 0;
        $page = 1;
        $paragraph = 1;
        $fehrest = 0;
        foreach ($rows as $k => $v) {
            $kx = $k;
            $fehrest = $v->index ? $v->index : $fehrest;
            if (!$fehrest) {
                do {
                    $fehrest = intval($rows[$kx]->index);
                    $kx = $fehrest ? $kx : $kx + 1;
                } while ($fehrest == 0 && isset($rows[$kx]));
            }
            $pc = $pc ? $pc : array_shift($pages);
            $data = array(
                "page" => $page,
                "paragraph" => $paragraph,
                "fehrest" => $fehrest
            );
            $this->db->where('id', $v->id)->update('book_meta', $data);
            if ($pc <= $v->order) {
                $page++;
                $pc = 0;
                $paragraph = 0;
            }
            $paragraph++;
        }

        $count = (int)$this->db->where('book_id', $id)->count_all_results('book_meta');
        $Xdata = array("part_count" => $count);
        $this->db->where('id', $id)->update('posts', $Xdata);

        $count = (int)$this->db->where('book_id', $id)->where('description IS NOT NULL')->count_all_results('book_meta');
        $Xdata = array("has_description" => $count);
        $this->db->where('id', $id)->update('posts', $Xdata);

        $count = (int)$this->db->where('book_id', $id)->where('sound IS NOT NULL')->count_all_results('book_meta');
        $Xdata = array("has_sound" => $count);
        $this->db->where('id', $id)->update('posts', $Xdata);

        $count = (int)$this->db->where('book_id', $id)->where('video IS NOT NULL')->count_all_results('book_meta');
        $Xdata = array("has_video" => $count);
        $this->db->where('id', $id)->update('posts', $Xdata);

        $count = (int)$this->db->where('book_id', $id)->where('image IS NOT NULL')->count_all_results('book_meta');
        $Xdata = array("has_image" => $count);
        $this->db->where('id', $id)->update('posts', $Xdata);

        $O = $this->db->select('COUNT(id) C')->where('book_id', $id)->get('user_books')->row();
        $count = $O->C;
        $Xdata = array("has_download" => $count);
        $this->db->where('id', $id)->update('posts', $Xdata);

        $O = $this->db->select('book_id,SUM(IF( `text` IS NULL ,0,LENGTH(`text`)))	+
			SUM(IF( `description` IS NULL ,0,LENGTH(`description`))) +	
			SUM(IF( `sound` IS NULL ,0,LENGTH(`sound`)))+
			SUM(IF( `video` IS NULL ,0,LENGTH(`video`))) +	
			SUM(IF( `image` IS NULL ,0,LENGTH(`image`))) AS C')->where('book_id', $id)->get('book_meta')->row();

        $count = $O->C;
        $Xdata = array("size" => $count);
        $this->db->where('id', $id)->update('posts', $Xdata);

        echo $this->MakeJSON($result);
    }


    public function category($op = "add")
    {
        $this->load->model('admin/m_post', 'post');

        $done = FALSE;
        $msg = "انجام نشد";
        $return = array();
        $data = $this->input->post();

        switch ($op) {
            case 'add':
                if ($return = $this->post->addCategory($data)) {
                    $done = true;
                    $msg = "ذخیره شد";
                    $return->menu = $this->post->getCategorySelectMenu($data['type'], 0);
                }
                break;
            case 'update':
                if ($return = $this->post->updateCategory($data)) {
                    $done = true;
                    $msg = "ذخیره شد";
                    $return->menu = $this->post->getCategorySelectMenu($data['type'], 0);
                }
                break;
            case 'delete':
                if (isset($data['id']) && $return = $this->post->deleteCategory($data['id'])) {
                    $done = true;
                    $msg = "حذف شد";
                }
                break;
        }
        $data = array('done' => $done, 'msg' => $msg, 'data' => $return);
        echo $this->MakeJSON($data);
    }

    public function saveSetting()
    {
        try {
            $data = $this->input->post('data');

            if (!$data)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            foreach ($data as $key => $value) {
                if ($this->db->where('name', $key)->count_all_results('settings'))
                    $this->db->set('value', $value)->where('name', $key)->update('settings');
                else
                    $this->db->insert('settings', array('name' => $key, 'value' => $value));
            }

            $this->tools->outS(0, 'ذخیره شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function getCommentInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('read_comment')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('comments'))
                throw new Exception('این نظر حذف شده است', 2);

            $msg = $this->db->where('id', $id)->get('comments')->row();

            $this->tools->outS(0, 'OK', array('info' => $msg));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function updateComment($id = NULL)
    {
        $data = $this->input->post();

        try {
            if (!$data or !$id)
                throw new Exception("اطلاعات ارسالی صحیح نیست", 1);

            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('comments'))
                throw new Exception('این نظر حذف شده است', 2);

            $this->load->library('form_validation');

            $this->form_validation->set_rules('name', 'نام', 'trim|required|xss_clean|max_length[30]');

            $this->form_validation->set_rules('text', 'نظر', 'trim|required|xss_clean|max_length[3000]');

            if ($this->form_validation->run() == FALSE)
                throw new Exception(validation_errors(), 2);

            $data = array(
                'name' => $this->input->post('name'),
                'text' => $this->input->post('text'),
                'email' => $this->input->post('email'),
            );

            if (!$id = $this->db->where('id', $id)->update('comments', $data))
                throw new Exception("خطا در انجام عملیات", 1);

            $msg = " ثبت شد !";
            $this->tools->outS(0, $msg);
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function replyComment($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception("اطلاعات ارسالی صحیح نیست", 1);

            $id = intval($id);
            $comment = $this->db->where('id', $id)->get('comments')->row();

            if (!$comment)
                throw new Exception('این نظر حذف شده است', 1);

            $this->load->library('form_validation');

            $this->form_validation->set_rules('r-text', 'نظر', 'trim|required|xss_clean|max_length[3000]');

            if ($this->form_validation->run() == FALSE)
                throw new Exception(validation_errors(), 2);


            $text = $this->input->post('r-text');
            $data = array(
                'table' => $comment->table,
                'row_id' => $comment->row_id,
                'submitted' => 1,
                'user_id' => $this->user->data->id,
                'name' => $this->user->data->displayname,
                'text' => $text,
                'ip' => $this->input->ip_address(),
                'email' => $this->user->data->email,
                'date' => date('Y-m-d H:i:s'),
                'parent' => $comment->id,
            );

            if (!$this->db->insert('comments', $data))
                throw new Exception("خطا در انجام عملیات", 1);

            $msg = "نظر ثبت شد !";
            $this->tools->outS(0, $msg);
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function getQuestionInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('read_msg')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('questions'))
                throw new Exception('این پشتیبانی جزئیات ندارد', 2);

            $msg0 = $this->db->where('id', $id)->get('questions')->result();
            $msg1 = $this->db->where('qid', $id)->get('questions')->result();
            $msg = array_merge($msg0, $msg1);
            $this->tools->outS(0, 'OK', array('info' => $msg));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function messageInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('read_msg')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('admin_inbox'))
                throw new Exception('این پیام حذف شده است', 2);

            $this->db->where('id', $id)->set('visited', 1)->update('admin_inbox');
            $msg = $this->db->where('id', $id)->get('admin_inbox')->row();

            $this->tools->outS(0, 'OK', array('info' => $msg));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function replyMsg($id)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعات ارسالی صحیح نمی باشند', 1);

            $this->load->library('form_validation');
            $this->form_validation->set_rules('ansver', 'متن پاسخ', 'trim|xss_clean|required');
            $this->form_validation->set_rules('subject', 'موضوع', 'trim|xss_clean');

            if ($this->form_validation->run() == FALSE)
                throw new Exception(validation_errors(), 2);

            $ansver = $this->input->post('ansver');
            $subject = $this->input->post('subject');

            if (!$this->user->can('reply_msg')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('admin_inbox'))
                throw new Exception('این پیام حذف شده است', 2);

            $msg = $this->db->where('id', $id)->get('admin_inbox')->row();

            if (!$this->tools->sendEmail($msg->email, $subject, $ansver))
                throw new Exception('پیام ارسال نشد', 2);

            $this->db->where('id', $id)->set('ansver', $ansver)->update('admin_inbox');

            $this->tools->outS(0, 'پیام شما ارسال شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteRow($table = NULL, $id = NULL)
    {
        try {
            if (!isset($id) or empty($id) or !isset($table) or empty($table))
                throw new Exception("اطلاعات ارسالی صحیح نیست", 2);

            if (!$this->db->table_exists($table))
                throw new Exception("امکان انجام این عملیات وجود ندارد", 1);

            $access = array(
                'admin_inbox' => $this->user->can('delete_msg'),
                'comments' => $this->user->can('delete_comment'),
                'instruments' => $this->user->can('delete_tools'),
                'logs' => FALSE,
                'onlines' => FALSE,
                'posts' => FALSE,
                'post_meta' => FALSE,
                'rates' => FALSE,
                'settings' => FALSE,
                'users' => $this->user->can('delete_user'),
                'user_level' => FALSE,
            );

            if (isset($access[$table]) && !$access[$table])
                throw new Exception("شما به این قسمت دسترسی ندارید", 2);

            if ($table == 'users' && $id == 1)
                throw new Exception("مدیر نمی تواند حذف شود", 1);

            if (!$this->tools->deleteRow($table, $id))
                throw new Exception("خطا در انجام عملیات", 1);
            if ($table == 'questions') {
                $this->db->where('qid', $id)->delete('questions');
            }
            $this->tools->outS(0, "حذف شد");
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function toggleField()
    {
        $data = $this->input->post();

        try {
            if (!isset($data['table'], $data['field'], $data['id']))
                throw new Exception("اطلاعات ارسالی صحیح نیست", 2);

            if (!$this->tools->toggleField($data['table'], $data['field'], $data['id']))
                throw new Exception("خطا در انجام عملیات", 1);
            if ($data['table'] == 'questions' && $data['field'] == 'published') {
                $row = $this->db->where('id', $data['id'])->get('questions')->row();
                $xdata = array('published' => $row->published);
                $this->db->where('qid', $data['id'])->update('questions', $xdata);
            }
            $this->tools->outS(0, "انجام شد");

        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * GROUP
     ******************************/

    public function addGroup()
    {
        try {
            $this->load->library('form_validation');
            $this->form_validation->set_rules('name', 'نام گروه', 'trim|xss_clean|required');

            if ($this->form_validation->run() == FALSE)
                throw new Exception(validation_errors(), 2);

            if (!$this->user->can('edit_group'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $name = $this->input->post('name');

            if ($this->db->where('name', $name)->where('parent', 0)->count_all_results('group'))
                throw new Exception("گروهی با این نام قبلا ایجاد شده است", 2);

            $data = array(
                'name' => $name,
                'parent' => 0,
            );

            if (!$this->db->insert('group', $data))
                throw new Exception("خطا در انجام عملیات", 1);

            $this->tools->outS(0, "انجام شد");
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function addChild()
    {
        try {
            $this->load->library('form_validation');
            $this->form_validation->set_rules('name', 'نام گروه', 'trim|xss_clean|required');
            $this->form_validation->set_rules('parent', 'والد', 'trim|xss_clean|required');
            $this->form_validation->set_rules('position', 'موقعیت', 'trim|xss_clean|required');
            $this->form_validation->set_rules('book_id', 'کتاب', 'trim|xss_clean|required');

            if ($this->form_validation->run() == FALSE)
                throw new Exception(validation_errors(), 2);

            if (!$this->user->can('edit_group'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $name = $this->input->post('name');
            $parent = $this->input->post('parent');
            $book_id = $this->input->post('book_id');

            if ($this->db->where('name', $name)->where('parent', $parent)->count_all_results('group'))
                throw new Exception("گروهی با این نام قبلا ایجاد شده است", 2);
            $row = $this->db->where('parent', $parent)->order_by('position DESC')->get('group')->row();
            $position = intval(@$row->position) + 1;
            $data = array(
                'name' => $name,
                'book_id' => $book_id,
                'parent' => $parent,
                'position' => $position
            );

            if (!$this->db->insert('group', $data))
                throw new Exception("خطا در انجام عملیات", 1);

            $id = $this->db->insert_id();

            $row = $this->db->where('id', $id)->get('group')->row();

            $this->tools->outS(0, "انجام شد", array('row' => $row));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteGroup($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('delete_group'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $id = intval($id);

            $this->load->model('m_group', 'group');

            if (!$this->group->deleteGroup($id))
                throw new Exception("خطا در انجام عملیات", 1);

            $this->tools->outS(0, 'حذف شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function saveList()
    {
        try {
            if (!$this->user->can('edit_group'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $data = $this->input->post('data');

            foreach ($data as $item) {
                $id = $item['id'];
                unset($item['id']);
                if (count($item))
                    $this->db->where('id', $id)->update('group', $item);

            }
            /*if( ! $this->group->deleteGroup($id) )
                throw new Exception("خطا در انجام عملیات" , 1);*/


            $this->tools->outS(0, 'اطلاعات ذخیره شد', array('data' => $data));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function factorCancel($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            $id = intval($id);
            $html = '';
            $book_html = '';
            $f = $this->db->where('id', $id)->get('factors', 1)->row();

            if (!isset($f->id))
                throw new Exception('فاکتور مورد نظر پیدا نشد', 1);

            $data = array('status' => 2);
            $this->db->where('id', $id)->update('factors', $data);

            $this->tools->outS(0, 'برگشت خورد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function factorXLSX()
    {
        include_once("xlsxwriter.class.php");
        $data = $this->input->post();
        $query = $data['query'];
        $ownerpercent = $data['ownerpercent'];
        $query = str_replace('`', '', $query);
        $query = str_replace('where', '', $query);
        list($query, $orderby) = explode('order by', $query);
        $query = explode('AND', $query);
        $this->db->select('f.*,ROUND(f.price*(' . $ownerpercent . '/100)) price, u.id as uid, u.email , u.tel, u.name, u.family, u.username');
        foreach ($query as $k => $v)
            $this->db->where($v);
        $this->db->order_by($orderby);
        $this->db->limit(3000);
        $this->db->join('ci_users u', 'u.id=f.user_id', 'inner', FALSE);
        $rows = $this->db->get('factors f')->result();
        if (!count($rows)) {
            $backurl = 'admin/payment' . (strlen($data['backurl']) ? '?' . $data['backurl'] : '');
            die($backurl);
            redirect(base_url() . 'admin/payment');
        }

        $fields = array('id', 'ref_id', 'price', 'username', 'tel', 'status', 'state', 'cdate', 'pdate');
        $header = array("#" => 'string', 'شماره فاکتور' => 'string', 'شماره رسید' => 'string', 'قیمت' => 'string', 'کاربر' => 'string', 'تلفن' => 'string', 'وضعیت' => 'string', 'نتیجه' => 'string', 'تاریخ ایجاد' => 'string', 'تاریخ پرداخت' => 'string');

        $data = array();
        $Xpersonelid = array();
        $lastid = 1;
        foreach ($rows as $k => $item) {
            $data[$k][] = $lastid++;
            foreach ($fields as $k1 => $v1) {
                if (in_array($v1, array('cdate', 'pdate'))) {
                    $X = $item->{$v1};
                    $data[$k][] = $X ? jdate("Y/m/d H:i:s", $X, "", "", "en") : '';
                } else {
                    $X = $item->{$v1};
                    $data[$k][] = $X;
                }
            }
        }

        $writer = new XLSXWriter();
        $writer->setAuthor($this->user->data->username);

        $writer->writeSheet($data, 'فاکتور فروش', $header);
        $rand = rand(111111, 999999);
        $filename = "factor$rand.xlsx";

        header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename($filename) . '"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        $writer->writeToStdOut();
        exit(0);
    }

    public function EnCode($str)
    {
        $str = json_encode($str);
        $str = base64_encode($str);
        $str = str_replace("=", "ARB", $str);
        return $str;
    }

    public function DeCode($str)
    {
        $str = str_replace("ARB", "=", $str);
        $str = base64_decode($str);
        $str = json_decode($str);
        return $str;
    }

    public function gozareshXLSX()
    {
        include_once("xlsxwriter.class.php");
        $data = $this->input->post();
        $query = $data['query'];
        $ownerpercent = $data['ownerpercent'];
        $query = str_replace('`', '', $query);
        $query = str_replace('where', '', $query);
        list($query, $orderby) = explode('order by', $query);
        $query = explode('AND', $query);

        $this->db->select("d.*,f.cdate,f.pdate, CONCAT(u.username,' [ ',u.displayname,']') `userdata`,p.title");
        foreach ($query as $k => $v)
            $this->db->where($v);
        $this->db->order_by($orderby);
        $this->db->limit(3000);
        $this->db->join('ci_factors f', 'f.id = d.factor_id', 'inner', FALSE);
        $this->db->join('ci_posts p', 'p.id = d.book_id', 'inner', FALSE);
        $this->db->join('ci_users u', 'u.id = p.author', 'inner', FALSE);
        $rows = $this->db->get('factor_detail d')->result();

        if (!count($rows)) {
            $backurl = 'admin/payment' . (strlen($data['backurl']) ? '?' . $data['backurl'] : '');
            die($backurl);
            redirect(base_url() . 'admin/payment');
        }

        $fields = array('counter', 'factor_id', 'discount', 'userdata', 'title', 'pdate');
        $header = array('ردیف' => 'string', 'شماره فاکتور' => 'string', 'قیمت' => 'string', 'کاربر' => 'string', 'کتاب' => 'string', 'تاریخ پرداخت' => 'string');

        $data = array();
        $Xpersonelid = array();
        $lastid = 0;
        foreach ($rows as $k => $item) {
            $lastid++;
            foreach ($fields as $k1 => $v1) {
                if (in_array($v1, array('cdate', 'pdate'))) {
                    $X = $item->{$v1};
                    $data[$k][] = $X ? jdate("Y/m/d H:i:s", $X, "", "", "en") : '';
                } elseif ($v1 == 'counter') {
                    $X = $lastid;
                    $data[$k][] = $X;
                } else {
                    $X = $item->{$v1};
                    $data[$k][] = $X;
                }
            }
        }


        $writer = new XLSXWriter();
        $writer->setAuthor($this->user->data->username);

        $writer->writeSheet($data, 'گزارش فروش', $header);
        $rand = rand(111111, 999999);
        $filename = "factor$rand.xlsx";

        header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename($filename) . '"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        $writer->writeToStdOut();
        exit(0);
    }

    public function salereportXLSX()
    {
        include_once("xlsxwriter.class.php");
        $data = $this->input->post();
        $query = $data['query'];
        $ownerpercent = $data['ownerpercent'];
        $query = str_replace('`', '', $query);
        $query = str_replace(array('GROUP BY d.book_id', 'where'), '', $query);
        list($query, $orderby) = explode('order by', $query);
        $query = explode('AND', $query);


        $this->db->select("SUM(d.discount) sumprice,CONCAT(u.username,' [ ',u.displayname,']') `userdata`,p.title");
        foreach ($query as $k => $v)
            $this->db->where($v);
        $this->db->order_by($orderby);
        $this->db->group_by('d.book_id');
        $this->db->limit(3000);
        $this->db->join('ci_factors f', 'f.id = d.factor_id', 'inner', FALSE);
        $this->db->join('ci_posts p', 'p.id = d.book_id', 'inner', FALSE);
        $this->db->join('ci_users u', 'u.id = p.author', 'inner', FALSE);
        $rows = $this->db->get('factor_detail d')->result();

        if (!count($rows)) {
            $backurl = 'admin/payment' . (strlen($data['backurl']) ? '?' . $data['backurl'] : '');
            die($backurl);
            redirect(base_url() . 'admin/payment');
        }

        $fields = array('counter', 'sumprice', 'userdata', 'title');
        $header = array('ردیف' => 'string', 'مبلغ فروخته شده تا کنون' => 'string', 'کاربر' => 'string', 'کتاب' => 'string');

        $data = array();
        $Xpersonelid = array();
        $lastid = 0;
        foreach ($rows as $k => $item) {
            $lastid++;
            foreach ($fields as $k1 => $v1) {
                if (in_array($v1, array('cdate', 'pdate'))) {
                    $X = $item->{$v1};
                    $data[$k][] = $X ? jdate("Y/m/d H:i:s", $X, "", "", "en") : '';
                } elseif ($v1 == 'counter') {
                    $X = $lastid;
                    $data[$k][] = $X;
                } else {
                    $X = $item->{$v1};
                    $data[$k][] = $X;
                }
            }
        }


        $writer = new XLSXWriter();
        $writer->setAuthor($this->user->data->username);

        $writer->writeSheet($data, 'گزارش فروش', $header);
        $rand = rand(111111, 999999);
        $filename = "factor$rand.xlsx";
        header('Content-disposition: attachment; filename="' . XLSXWriter::sanitize_filename($filename) . '"');
        header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        $writer->writeToStdOut();
        exit(0);
    }

    public function factorDetails($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            $id = intval($id);
            $html = '';
            $book_html = '';
            $f = $this->db->where('id', $id)->get('factors', 1)->row();

            if (!isset($f->id)) {
                throw new Exception('فاکتور مورد نظر پیدا نشد', 1);
            }

            $books = $this->post->getPosts([
                'user_id' => $f->user_id,
                'type' => 'book',
                'where' => ["p.id IN (SELECT `book_id` FROM `ci_user_books` WHERE `factor_id`={$id})"],
            ]);

            foreach ($books as $book) {
                $book_html .=
                    "<tr>
					<td class=en><b>#{$book->id}</b></td>
					<td>{$book->title}</td>
					<td>" . number_format($book->price) . " تومان</td>
				</tr>";
            }

            $html =
                "<div class=\"row\">
				<div class=\"col-sm-6\">
					<div class=\"alert alert-info\">جزئیات فاکتور</div>
					<table class=\"table light2\">
						<tr>
							<th>شماره فاکتور</th>
							<th><b class=\"en\" style=\"font-size:16px;\">#{$f->id}</b></th>
						</tr>
						<tr>
							<td>رسید دیجیتالی</td>
							<td class=en>{$f->ref_id}</td>
						</tr>
						<tr>
							<td>وضعیت</td>
							<td>"
                . (
                ($f->status == 0 && $f->status != '') ?
                    '<span class="text-success"> <i class="fa fa-check-circle-o fa-lg"></i>  موفق </span>' :
                    (
                    $f->status == 1 ?
                        '<span class="text-danger"> <i class="fa fa-ban fa-lg"></i> ناموفق</span>' : (
                    $f->status == 2 ?
                        '<span class="text-primary"> <i class="fa fa-mail-reply fa-lg"></i> برگشت خورده</span>' :
                        ' <i class="fa fa-spinner fa-lg text-muted"></i> در انتظار'
                    )
                    )
                ) .
                "</td>
						</tr>
						<tr>
							<td>نتیجه</td>
							<td>{$f->state}</td>
						</tr>
						<tr>
							<td>مبلغ فاکتور</td>
							<td>" . number_format($f->cprice) . " تومان</td>
						</tr>
						<tr>
							<td>تخفیف لحاظ شده</td>
							<td class=en>{$f->discount}%</td>
						</tr>
						<tr>
							<td>مبلغ برای پرداخت</td>
							<td>" . number_format($f->price) . " تومان</td>
						</tr>
						<tr>
							<td>مبلغ  پرداخت شده</td>
							<td>" . number_format($f->paid) . " تومان</td>
						</tr>
						<tr>
							<td>تاریخ ایجاد</td>
							<td>" . jdate('d F y - H:i', $f->cdate) . "</td>
						</tr>
						<tr>
							<td>تاریخ پرداخت</td>
							<td>" . ($f->pdate != '' ? jdate('d F y - H:i', $f->pdate) : '---') . "</td>
						</tr>
					</table>				
				</div>
				<div class=\"col-sm-6\">
					<div class=\"alert alert-info\">لیست کتابهای خریداره شده</div>
					<table class=\"table light2\">
					<tr>
						<th>شماره</th>
						<th>نام</th>
						<th>قیمت</th>
					</tr>
					{$book_html}
					</table>
				</div>
				<div class=\"col-sm-12\">
					<div class=\"text-muted\" style=\"margin:30px 0 0 0\">قیمت کتاب ها در سمت چپ مربوط به اکنون می باشد و قیمت  لحاظ شده در فاکتور مربوط به تاریخ ایجاد فاکتور است.</div>
				</div>				
			</div>";

            $this->tools->outS(0, 'حذف شد', ['html' => $html]);
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * Azmoon
     ******************************/
    public function addAzmoon()
    {
        try {
            if (!$this->user->can('manage_post'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);
            $file_temp = $_FILES['excelfile']['tmp_name'];
            $file_name = $_FILES['excelfile']['name'];

            $this->load->library('form_validation');
            $this->form_validation->set_rules('exceltype', 'نوع آزمون', 'trim|xss_clean|required|alpha_numeric');
            if (!strlen($file_name))
                $this->form_validation->set_rules('excelfile', 'فایل اکسل آزمونها', 'trim|required|file');

            if ($this->form_validation->run() == FALSE)
                throw new Exception(validation_errors(), 2);
            require(BASEPATH . 'excelfile/excel_reader.php');
            require(BASEPATH . 'excelfile/SpreadsheetReader.php');
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $Filepath = FCPATH . 'temp/' . uniqid() . '.' . $ext;
            move_uploaded_file($file_temp, $Filepath);

            $Spreadsheet = new SpreadsheetReader($Filepath);
            $BaseMem = memory_get_usage();

            $Sheets = $Spreadsheet->Sheets();
            echo '<link type="text/css" rel="stylesheet" href="' . base_url() . 'style/_master/css/_admin/bootstrap.css">';
            echo '<script type="text/javascript" src="' . base_url() . 'js/jquery.min.js"></script>';
            $data = $this->input->post();
            $exceltype = $data["exceltype"];
            $data = array();
            foreach ($Sheets as $Index => $Name) {
                echo '<p>&nbsp;</p>';
                echo '<p>&nbsp;</p>';
                echo '<p>&nbsp;</p>';
                echo '<p>&nbsp;</p>';
                echo '<p>&nbsp;</p>';
                echo '<center>*** Sheet ' . $Name . ' ***</center>';
                echo '<center>---------------------------------</center>';
                $Time = microtime(true);
                $Spreadsheet->ChangeSheet($Index);
                echo '<table class="table" dir="rtl">';
                foreach ($Spreadsheet as $Key => $Row) {
                    echo '<tr id="row' . $Key . '">';
                    if ($Row) {
                        if ($Key == 1) {
                            echo '<th>ردیف</th>';
                            foreach ($Row as $k => $v) {
                                echo '<th>' . $v . '</th>';
                            }
                        } else {
                            $data[$Key] = array();
                            echo '<th>' . ($Key - 1) . '</th>';
                            foreach ($Row as $k => $v) {
                                echo '<td>' . $v . '</td>';
                                $data[$Key][] = $v;
                            }
                        }
                    } else {
                        var_dump($Row);
                    }
                    $CurrentMem = memory_get_usage();
                    echo '</tr>';
                }
                echo '</table>';

            }
            $alert = array();
            switch ($exceltype) {
                case "tests":
                    foreach ($data as $k => $v) {
                        $i = 0;
                        $testsData = [
                            'category' => $v[$i],
                            'term' => $v[$i++],
                            'book_id' => $v[$i++],
                            'testnumber' => $v[$i++],
                            'page' => $v[$i++],
                            'question' => $v[$i++],
                            'answer_1' => $v[$i++],
                            'answer_2' => $v[$i++],
                            'answer_3' => $v[$i++],
                            'answer_4' => $v[$i++],
                            'true_answer' => $v[$i++]
                        ];
                        $this->db->select("id");
                        $this->db->where("book_id", $v[1]);
                        $this->db->where("question", $v[4]);
                        $this->db->where("answer_1", $v[5]);
                        $O = $this->db->get($exceltype)->result();
                        if (count($O) == 0) {
                            $this->db->insert($exceltype, $testsData);
                            $data = array('date_modified' => date("Y-m-d H:i:s"));
                            $this->db->where('id', $v[1])->update('posts', $data);
                        } else {
                            $this->db->where('id', $O[0]->id)->update($exceltype, $testsData);
                            $alert[] = "#row" . $k;
                        }
                    }
                    break;
                case "tashrihi":
                    foreach ($data as $k => $v) {
                        $i = 0;
                        $testsData = [
                            'category' => $v[$i],
                            'term' => $v[$i++],
                            'book_id' => $v[$i++],
                            'testnumber' => $v[$i++],
                            'page' => $v[$i++],
                            'barom' => $v[$i++],
                            'question' => $v[$i++],
                            'answer' => $v[$i++]
                        ];
                        $this->db->select("id");
                        $this->db->where("book_id", $v[1]);
                        $this->db->where("question", $v[5]);
                        $O = $this->db->get($exceltype)->result();
                        if (count($O) == 0) {
                            $this->db->insert($exceltype, $testsData);
                            $data = array('date_modified' => date("Y-m-d H:i:s"));
                            $this->db->where('id', $v[1])->update('posts', $data);
                        } else {
                            $this->db->where('id', $O[0]->id)->update($exceltype, $testsData);
                            $alert[] = "#row" . $k;
                        }
                    }
                    break;
            }
            echo '<center>
				<div class="dropdown-backdrop" style="height: 100px;">
					<div class="btn btn-success"><h1>اطلاعات ذخیره شد' . (count($alert) ? ' <strong>[ موارد تکراری قرمز شد ]</strong>' : '') . '</h1></div>
					<a href="' . base_url() . 'admin/azmoon" class="btn btn-primary pull-left"><h2>بازگشت</h2></a>
				</div>
				</center>';
            if (count($alert)) {
                echo '<script>';
                echo '$("' . implode(",", $alert) . '").attr("style","color:white;background-color:red;");';
                echo '</script>';
            }
        } catch (Exception $e) {
            echo '<center>
				<div class="dropdown-backdrop" style="height: 100px;">
					<div class="btn btn-danger"><h1>اطلاعات ذخیره نشد</h1>' . $e->getMessage() . '</div>
					<a href="' . base_url() . 'admin/azmoon" class="btn btn-primary pull-left"><h2>بازگشت</h2></a>
				</div>
				</center>';
        }
    }

    /******************************
     * Catquest
     ******************************/
    public function SaveCatquest()
    {
        try {
            if (!$this->user->can('manage_catquest'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            if ($id)
                $this->form_validation->set_rules('title', 'زبان', 'trim|required|is_unique[catquest.title,catquest.id.' . $id . ']');
            else
                $this->form_validation->set_rules('title', 'زبان', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $title = $post["title"];
            $regdate = date("Y-m-d H:i:s");
            $data = array("title" => $title, "regdate" => $regdate);
            if ($id) {
                if (!$this->db->where('id', $id)->update('catquest', $data))
                    throw new Exception("خطا در انجام عملیات", 1);
            } else {
                $data["uid"] = $this->user->user_id;
                $this->db->insert('catquest', $data);
            }
            $this->tools->outS(0, 'زبان ' . $title . ' ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * Publisher
     ******************************/
    public function SavePublisher()
    {
        try {
            if (!$this->user->can('manage_publisher'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            if ($id)
                $this->form_validation->set_rules('title', 'نام انتشارات', 'trim|required|is_unique[publisher.title,publisher.id.' . $id . ']');
            else
                $this->form_validation->set_rules('title', 'نام انتشارات', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $title = $post["title"];
            $regdate = date("Y-m-d H:i:s");
            $data = array("title" => $title, "regdate" => $regdate);
            if ($id) {
                if (!$this->db->where('id', $id)->update('publisher', $data))
                    throw new Exception("خطا در انجام عملیات", 1);
            } else {
                $data["uid"] = $this->user->user_id;
                $this->db->insert('publisher', $data);
            }
            $this->tools->outS(0, 'انتشارات ' . $title . ' ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * Writer
     ******************************/
    public function SaveWriter()
    {
        try {
            if (!$this->user->can('manage_writer'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            if ($id)
                $this->form_validation->set_rules('title', 'نام نویسنده', 'trim|required|is_unique[writer.title,writer.id.' . $id . ']');
            else
                $this->form_validation->set_rules('title', 'نام نویسنده', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $title = $post["title"];
            $regdate = date("Y-m-d H:i:s");
            $data = array("title" => $title, "regdate" => $regdate);
            if ($id) {
                if (!$this->db->where('id', $id)->update('writer', $data))
                    throw new Exception("خطا در انجام عملیات", 1);
            } else {
                $data["uid"] = $this->user->user_id;
                $this->db->insert('writer', $data);
            }
            $this->tools->outS(0, 'نویسنده ' . $title . ' ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * Translator
     ******************************/
    public function SaveTranslator()
    {
        try {
            if (!$this->user->can('manage_translator'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            if ($id)
                $this->form_validation->set_rules('title', 'نام انتشارات', 'trim|required|is_unique[translator.title,translator.id.' . $id . ']');
            else
                $this->form_validation->set_rules('title', 'نام انتشارات', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $title = $post["title"];
            $regdate = date("Y-m-d H:i:s");
            $data = array("title" => $title, "regdate" => $regdate);
            if ($id) {
                if (!$this->db->where('id', $id)->update('translator', $data))
                    throw new Exception("خطا در انجام عملیات", 1);
            } else {
                $data["uid"] = $this->user->user_id;
                $this->db->insert('translator', $data);
            }
            $this->tools->outS(0, 'مترجم ' . $title . ' ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * Supplier
     ******************************/
    public function getSupplierInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_suppliers')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('supplier'))
                throw new Exception('این عرضه کننده حذف شده است', 2);

            $supplier = $this->db->where('id', $id)->get('supplier')->row();
            $stype = array();
            $O = $this->db->where('sup_id', $id)->get('supplierrules')->result();
            foreach ($O as $k => $v) {
                $stype[] = $v->type_id;
            }
            $supplier->stype = $stype;

            $this->tools->outS(0, 'OK', array('supplier' => $supplier));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveSuppliertype()
    {
        try {
            if (!$this->user->can('manage_suppliertype'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            if ($id)
                $this->form_validation->set_rules('title', 'نوع', 'trim|required|is_unique[suppliertype.title,suppliertype.id.' . $id . ']');
            else
                $this->form_validation->set_rules('title', 'نوع', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $datatype = $post["datatype"];
            $title = $post["title"];
            $regdate = date("Y-m-d H:i:s");
            $data = array("datatype" => $datatype, "title" => $title, "regdate" => $regdate, "isplace" => ($datatype == "place" ? 1 : 0));
            if ($id) {
                if (!$this->db->where('id', $id)->update('suppliertype', $data)) {
                    throw new Exception("خطا در انجام عملیات", 1);
                }
            } else {
                $data["uid"] = $this->user->user_id;
                $this->db->insert('suppliertype', $data);
            }
            $this->tools->outS(0, 'زبان ' . $title . ' ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveSupplier()
    {
        try {
            if (!$this->user->can('manage_supplier'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval(@$post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            $this->form_validation->set_rules('title', 'عنوان', 'trim|required|is_unique[supplier.title,supplier.id.' . $id . ']');
            if ($id) {
                $this->form_validation->set_rules('mobile', 'تلفن همراه', 'trim|required|is_unique[supplier.mobile,supplier.id.' . $id . ']');
            } else {
                $this->form_validation->set_rules('mobile', 'تلفن همراه', 'trim|required|is_unique[supplier.mobile]');
            }
            $this->form_validation->set_rules('ownerpercent', 'درصد مالکیت', 'trim|required|numeric');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }

            $regdate = date("Y-m-d H:i:s");
            $data = array(
                "title" => @$post["title"],
                "image" => @$post["image"],
                "description" => @$post["description"],
                "optype" => @$post["optype"],
                "phone" => @$post["phone"],
                "mobile" => @$post["mobile"],
                "smtype" => @$post["smtype"],
                "address" => @$post["address"],
                "ownerpercent" => @$post["ownerpercent"],
                "offer" => @$post["offer"],
                "regdate" => $regdate,
            );
            if ($id) {
                if (!$this->db->where('id', $id)->update('supplier', $data)) {
                    throw new Exception("خطا در انجام عملیات", 1);
                }
            } else {
                $data["uid"] = $this->user->user_id;
                $this->db->insert('supplier', $data);
                $id = $this->db->insert_id();
            }
            $this->db->where("sup_id", $id)->delete("supplierrules");
            if (isset($post["stype"]) && is_array($post["stype"]) && count($post["stype"])) {
                foreach ($post["stype"] as $stype) {
                    $this->db->insert("supplierrules", ["type_id" => $stype, "sup_id" => $id]);
                }
            }
            $message = $id ? '' . @$post["title"] . ' در لیست عرضه کنندگان بروزرسانی شد' : '' . @$post["title"] . ' در لیست عرضه کنندگان ثبت شد';
            $this->tools->outS(0, $message);
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * Geotype
     ******************************/
    public function SaveGeotype()
    {
        try {
            if (!$this->user->can('manage_geotype'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            if ($id)
                $this->form_validation->set_rules('title', 'نوع مناطق جغرافیایی', 'trim|required|is_unique[geotype.title,geotype.id.' . $id . ']');
            else
                $this->form_validation->set_rules('title', 'نوع مناطق جغرافیایی', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $title = $post["title"];
            $regdate = date("Y-m-d H:i:s");
            $data = array("title" => $title, "regdate" => $regdate);
            if ($id) {
                if (!$this->db->where('id', $id)->update('geotype', $data))
                    throw new Exception("خطا در انجام عملیات", 1);
            } else {
                $data["uid"] = $this->user->user_id;
                $this->db->insert('geotype', $data);
            }
            $this->tools->outS(0, 'نوع منطقه جغرافیایی ' . $title . ' ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function geosection($op = "add")
    {
        if (!$this->user->can('is_supplier'))
            throw new Exception('شما به این قسمت دسترسی ندارید', 2);
        $user_id = $this->user->user_id;
        $this->load->model('m_geosection', 'geosection');

        $done = FALSE;
        $msg = "انجام نشد";
        $return = array();
        $data = $this->input->post();
        if ($op == "add")
            $data['user_id'] = $user_id;
        if (isset($data['data_type'])) {
            $data_type = $data['data_type'];
            unset($data['data_type']);
        } else {
            $data_type = array();
        }


        switch ($op) {
            case 'add':
                if ($return = $this->geosection->addGeosection($data)) {
                    $tid = $return->id;
                    $done = true;
                    $msg = "ذخیره شد";
                    $return->menu = $this->geosection->getGeosectionSelectMenu(0);
                }
                break;
            case 'update':
                if ($return = $this->geosection->updateGeosection($data)) {
                    $done = true;
                    $msg = "ذخیره شد";
                    $tid = $data['id'];
                    $return->menu = $this->geosection->getGeosectionSelectMenu(0);
                }
                break;
            case 'delete':
                if (isset($data['id']) && $return = $this->geosection->deleteGeosection($data['id'])) {
                    $tid = $data['id'];
                    $done = true;
                    $msg = "حذف شد";
                }
                break;
        }
        $data = array('done' => $done, 'msg' => $msg, 'data' => $return);
        header('Content-Type: application/json');
        echo $this->MakeJSON($data);
        die;
    }

    /******************************
     * Nezam
     ******************************/
    public function nezam($op = "add")
    {
        if (!$this->user->can('is_supplier'))
            throw new Exception('شما به این قسمت دسترسی ندارید', 2);
        $user_id = $this->user->user_id;
        $this->load->model('m_nezam', 'nezam');

        $done = FALSE;
        $msg = "انجام نشد";
        $return = array();
        $data = $this->input->post();
        if ($op == "add")
            $data['user_id'] = $user_id;
        if (isset($data['data_type'])) {
            $data_type = $data['data_type'];
            unset($data['data_type']);
        } else {
            $data_type = array();
        }


        switch ($op) {
            case 'add':
                if ($return = $this->nezam->addNezam($data)) {
                    $tid = $return->id;
                    $done = true;
                    $msg = "ذخیره شد";
                    $return->menu = $this->nezam->getNezamSelectMenu(0);
                }
                break;
            case 'update':
                if ($return = $this->nezam->updateNezam($data)) {
                    $done = true;
                    $msg = "ذخیره شد";
                    $tid = $data['id'];
                    $return->menu = $this->nezam->getNezamSelectMenu(0);
                }
                break;
            case 'delete':
                if (isset($data['id']) && $return = $this->nezam->deleteNezam($data['id'])) {
                    $tid = $data['id'];
                    $done = true;
                    $msg = "حذف شد";
                }
                break;
        }
        $data = array('done' => $done, 'msg' => $msg, 'data' => $return);
        header('Content-Type: application/json');
        echo $this->MakeJSON($data);
        die;
    }

    /******************************
     * Dictionary
     ******************************/
    public function SaveDiclang()
    {
        try {
            if (!$this->user->can('manage_diclang'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            if ($id)
                $this->form_validation->set_rules('title', 'زبان', 'trim|required|is_unique[diclang.title,diclang.id.' . $id . ']');
            else
                $this->form_validation->set_rules('title', 'زبان', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $title = $post["title"];
            $regdate = date("Y-m-d H:i:s");
            $data = array("title" => $title, "regdate" => $regdate);
            if ($id) {
                if (!$this->db->where('id', $id)->update('diclang', $data))
                    throw new Exception("خطا در انجام عملیات", 1);
            } else {
                $data["uid"] = $this->user->user_id;
                $this->db->insert('diclang', $data);
            }
            $this->tools->outS(0, 'زبان ' . $title . ' ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveDictionary()
    {
        try {
            if (!$this->user->can('manage_dictionary'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            if ($id)
                $this->form_validation->set_rules('kalameh', 'کلمه', 'trim|required');
            else
                $this->form_validation->set_rules('kalameh', 'کلمه', 'trim|required');
            $this->form_validation->set_rules('translate', 'ترجمه', 'trim|required');
            $this->form_validation->set_rules('fromlang', 'زبان مبدا', 'trim|required|alpha_numeric');
            $this->form_validation->set_rules('tolang', 'زبان ترجمه', 'trim|required|alpha_numeric');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }


            $fromlang = (int)$post["fromlang"];
            $tolang = (int)$post["tolang"];
            $kalameh = $post["kalameh"];

            $this->db->select('*');
            $this->db->where("d.kalameh = '$kalameh'");
            $this->db->where("d.fromlang = $fromlang");
            $this->db->where("d.tolang = $tolang");
            $result = $this->db->get('dictionary d')->result();
            if (count($result)) {
                throw new Exception("خطا : کلمه $kalameh در زبان انتخابی قبلا ترجمه شده است", 1);
            }
            $translate = trim(strip_tags($post["translate"]));
            $regdate = date("Y-m-d H:i:s");
            $data = array("fromlang" => $fromlang, "tolang" => $tolang, "kalameh" => $kalameh, "translate" => $translate, "regdate" => $regdate);
            if ($id) {
                if (!$this->db->where('id', $id)->update('dictionary', $data))
                    throw new Exception("خطا در انجام عملیات", 1);
            } else {
                $data["uid"] = $this->user->user_id;
                $this->db->insert('dictionary', $data);
            }
            $this->tools->outS(0, 'کلمه ' . $kalameh . ' ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function LoadDicOffer($id)
    {
        $this->db->select("*");
        $this->db->where("kid", $id);
        $this->db->order_by("translate");
        $O = $this->db->get("dicoffer")->result();
        $cats = [];
        foreach ($O as $k => $v) {
            $cats[] = $v;
        }
        $this->tools->outS(0, $cats);
    }

    public function DeleteOffer($id)
    {
        $result = $this->db->where('id', $id)->select('*')->get('dicoffer')->row();
        if (!is_object($result))
            throw new Exception("خطا در انجام عملیات : ترجمه پیشنهادی یافت نشد", 1);
        $kid = $result->kid;
        $this->db->where('id', $id)->delete('dicoffer');
        $result = $this->db->where('kid', $kid)->select('COUNT(*) offer')->get('dicoffer')->row();
        if (!is_object($result))
            throw new Exception("خطا در انجام عملیات : ترجمه پیشنهادی یافت نشد", 1);
        $offer = $result->offer > 0 ? $result->offer - 1 : 0;
        $data = array("offer" => $offer);
        $this->db->where('id', $kid)->update('dictionary', $data);

        $this->tools->outS(0, 'OK');
    }

    public function ReplaceOffer($id)
    {

        $this->db->select("kid,translate");
        $this->db->where("id", $id);
        $O = $this->db->get("dicoffer")->row();
        if (!is_object($O))
            throw new Exception("خطا در انجام عملیات : ترجمه پیشنهادی یافت نشد", 1);
        $data = array();
        $data["translate"] = $O->translate;
        $kid = $O->kid;
        if (!$this->db->where('id', $kid)->update('dictionary', $data))
            throw new Exception("خطا در انجام عملیات", 1);
        $this->tools->outS(0, 'جایگزینی با موفقیت انجام شد');
    }

    /******************************
     * Discount
     ******************************/
    public function addDiscount()
    {
        try {
            if (!$this->user->can('manage_discount'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $user_id = $this->user->user_id;
            $level = $this->user->getUserLevel($user_id);
            $percent = 100;
            if ($level != "admin" && $this->user->can('is_supplier')) {
                $this->db->select("*");
                $this->db->where("mobile", $this->user->data->tel);
                $row = $this->db->get("supplier")->row();
                if ($row) {
                    $percent = $row->ownerpercent;
                }
            }

            $this->load->library('form_validation');
            $this->form_validation->set_rules('code', 'کد', 'trim|xss_clean|required|alpha_numeric|is_unique[discounts.code]');
            $this->form_validation->set_rules('percent', 'درصد', "trim|required|less_than_equal_to[$percent]");
            $this->form_validation->set_rules('maxallow', 'سقف استفاده', 'trim|required|greater_than[0]');
            $this->form_validation->set_rules('level[]', 'سطح', 'trim|xss_clean|required');
            $this->form_validation->set_rules('expdate', 'تاریخ انقضا', 'trim|xss_clean|date|required');//Alireza Balvardi

            if ($this->form_validation->run() == FALSE)
                throw new Exception(validation_errors(), 2);

            $data = $this->input->post();
            $expdate = explode("-", $data["expdate"]);//Alireza Balvardi
            $data["expdate"] = jmktime(23, 59, 59, $expdate[1], $expdate[2], $expdate[0]);//Alireza Balvardi
            if (isset($data['catmembership'])) {
                $data['catmembership'] = explode(",", $data['catmembership']);
            }
            foreach ($data['level'] as $key => $level) {
                switch ($level) {
                    case -3: // {
                        $value = $data['categoryid'];
                        $this->db->select("p.id,p.title,c.name,m.meta_value price,c.id cid");
                        $this->db->join('ci_category c', 'p.category=c.id', 'right', FALSE);
                        $this->db->order_by('c.id,p.title', 'asc');
                        $this->db->join('ci_post_meta m', 'm.post_id=p.id', 'inner', FALSE);
                        $this->db->where("m.meta_key = 'price'");
                        $this->db->where("m.meta_value != '0'");
                        $this->db->where("c.id IN($value)");
                        $result = $this->db->get('posts p')->result();

                        foreach ($result as $k => $v) {
                            $discountData = [
                                'code' => $data['code'],
                                'percent' => (int)$data['percent'],
                                'maxallow' => (int)$data['maxallow'],
                                'category_id' => -1,
                                'price' => $data['price'],
                                'expdate' => $data['expdate'],//Alireza Balvardi
                                'fee' => (int)$data['fee'],
                                'bookid' => $v->id,
                                'used' => 0,
                                'author' => $this->user->user_id,
                                'cdate' => time()
                            ];
                            $this->db->insert('discounts', $discountData);
                        }
                        break; // }
                    case -4: // {
                        $value = $data['multibookid'];
                        $this->db->select("p.id,p.title,c.name,m.meta_value price,c.id cid");
                        $this->db->join('ci_category c', 'p.category=c.id', 'right', FALSE);
                        $this->db->order_by('c.id,p.title', 'asc');
                        $this->db->join('ci_post_meta m', 'm.post_id=p.id', 'inner', FALSE);
                        $this->db->where("m.meta_key = 'price'");
                        $this->db->where("m.meta_value != '0'");
                        $this->db->where("p.id IN($value)");
                        $result = $this->db->get('posts p')->result();

                        foreach ($result as $k => $v) {
                            $discountData = [
                                'code' => $data['code'],
                                'percent' => (int)$data['percent'],
                                'maxallow' => (int)$data['maxallow'],
                                'category_id' => -1,
                                'price' => $data['price'],
                                'expdate' => $data['expdate'],//Alireza Balvardi
                                'fee' => (int)$data['fee'],
                                'bookid' => $v->id,
                                'used' => 0,
                                'author' => $this->user->user_id,
                                'cdate' => time()
                            ];
                            $this->db->insert('discounts', $discountData);
                        }
                        break; // }
                    case -5: // {
                        $value = $data['dorehid'];
                        $this->db->select("d.id");
                        $this->db->where("d.id IN($value)");
                        $result = $this->db->get('doreh d')->result();
                        foreach ($result as $k => $v) {
                            $discountData = [
                                'code' => $data['code'],
                                'percent' => (int)$data['percent'],
                                'maxallow' => (int)$data['maxallow'],
                                'category_id' => -5,
                                'price' => $data['price'],
                                'expdate' => $data['expdate'],//Alireza Balvardi
                                'fee' => (int)$data['fee'],
                                'bookid' => $v->id,
                                'used' => 0,
                                'author' => $this->user->user_id,
                                'cdate' => time()
                            ];
                            $this->db->insert('discounts', $discountData);
                        }
                        break; // }
                    case -6: // {
                        $value = $data['dorehclassid'];
                        $this->db->select("d.id");
                        $this->db->where("d.id IN($value)");
                        $result = $this->db->get('dorehclass d')->result();
                        foreach ($result as $k => $v) {
                            $discountData = [
                                'code' => $data['code'],
                                'percent' => (int)$data['percent'],
                                'maxallow' => (int)$data['maxallow'],
                                'category_id' => -6,
                                'price' => $data['price'],
                                'expdate' => $data['expdate'],//Alireza Balvardi
                                'fee' => (int)$data['fee'],
                                'bookid' => $v->id,
                                'used' => 0,
                                'author' => $this->user->user_id,
                                'cdate' => time()
                            ];
                            $this->db->insert('discounts', $discountData);
                        }
                        break; // }
                    case -81:
                    case -83:
                    case -86:
                    case -812:

                        $discountData = [
                            'code' => $data['code'],
                            'percent' => (int)$data['percent'],
                            'maxallow' => (int)$data['maxallow'],
                            'category_id' => (int)$level,
                            'price' => $data['price'],
                            'expdate' => $data['expdate'],//Alireza Balvardi
                            'fee' => (int)$data['fee'],
                            'bookid' => (int)$data['catmembership'][$key],
                            'used' => 0,
                            'author' => $this->user->user_id,
                            'cdate' => time()
                        ];
                        $this->db->insert('discounts', $discountData);
                        break; // }
                    default: // {
                        $discountData = [
                            'code' => $data['code'],
                            'percent' => (int)$data['percent'],
                            'maxallow' => (int)$data['maxallow'],
                            'category_id' => (int)$level,
                            'price' => $data['price'],
                            'expdate' => $data['expdate'],//Alireza Balvardi
                            'fee' => (int)$data['fee'],
                            'bookid' => (int)$data['bookid'],
                            'used' => 0,
                            'author' => $this->user->user_id,
                            'cdate' => time()
                        ];
                        $this->db->insert('discounts', $discountData);
                    // }
                }
            }

            $this->tools->outS(0, 'اطلاعات ذخیره شد', array('data' => $data));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    private function MakeJSON($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG);
    }//Alireza Balvardi

    /******************************
     * Doreh
     ******************************/
    public function getDorehInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_dorehs')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('doreh'))
                throw new Exception('این عرضه کننده حذف شده است', 2);

            $doreh = $this->db->where('id', $id)->get('doreh')->row();

            $this->tools->outS(0, 'OK', array('doreh' => $doreh));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveDoreh()
    {
        try {
            if (!$this->user->can('manage_doreh'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            $this->form_validation->set_rules('tecatid', 'اسم دوره', 'trim|required');
            $this->form_validation->set_rules('nezamid', 'نظام', 'trim|required');
            $this->form_validation->set_rules('placeid', 'محل برگزاری', 'trim|required');
            $this->form_validation->set_rules('tahsili_year', 'سال تحصیلی', 'trim|required');
            $this->form_validation->set_rules('offer', 'سطح پیشنهادی', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $data["id"] = intval($post["id"]);
            $data["supplierid"] = intval($post["supplierid"]);
            $data["published"] = intval($post["published"]);
            $data["tecatid"] = intval($post["tecatid"]);
            $data["nezamid"] = intval($post["nezamid"]);
            $data["placeid"] = intval($post["placeid"]);
            $data["description"] = $post["description"];
            $data["tahsili_year"] = $post["tahsili_year"];
            $data["offer"] = $post["offer"];
            $data["image"] = $post["image"];
            $data["user_id"] = $this->user->user_id;
            if ($data["id"]) {
                $this->db->where('id', $data["id"])->update('doreh', $data);
            } else {
                $this->db->insert('doreh', $data);
            }
            $this->tools->outS(0, 'دوره ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteDoreh($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('delete_doreh'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $id = intval($id);

            $this->load->model('m_doreh', 'doreh');

            if (!$this->doreh->delete($id))
                throw new Exception("خطا در انجام عملیات", 1);

            $this->tools->outS(0, 'دوره انتخابی حذف شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * DorehClass
     ******************************/
    public function getDorehClass($value = NULL)
    {
        try {
            if (!strlen($value))
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_discount')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $value = urldecode($value);
            $values = str_replace(",", "", $value);
            $this->db->select("dc.id,t.name,c.title,d.tahsili_year,SUM(dc.price) price");
            $this->db->join('ci_tecat t', 't.id=d.tecatid', 'inner', FALSE);
            $this->db->join('ci_dorehclass dc', 'dc.dorehid=d.id', 'inner', FALSE);
            $this->db->join('ci_classroom c', 'c.id=dc.classid', 'inner', FALSE);
            $this->db->group_by('d.id,t.name,d.tahsili_year');
            if (is_numeric($values)) {
                $this->db->where("c.id IN($value)");
            } else {
                $this->db->where("t.name LIKE '%$value%'");
                $this->db->or_where("c.title LIKE '%$value%'");
            }
            $result = $this->db->get('doreh d')->result();

            $data = array();
            foreach ($result as $k => $v) {
                if ($v->price)
                    $data[] = array("label" => "$v->title / $v->name (ID : $v->id)", "title" => "$v->title / $v->name [ $v->tahsili_year - " . ($v->tahsili_year + 1) . "]", "idx" => $v->id, "price" => $v->price);
            }
            $this->tools->outS(0, 'OK', array("result" => $data));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function getDorehClassInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_dorehs')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('dorehclass'))
                throw new Exception('این عرضه کننده حذف شده است', 2);

            $dorehclass = $this->db->where('id', $id)->get('dorehclass')->row();
            $startdate = date("Y-m-d", $dorehclass->startdate);
            $startdate = explode("-", $startdate);
            list($j_y, $j_m, $j_d) = gregorian_to_jalali($startdate[0], $startdate[1], $startdate[2]);
            $dorehclass->startdate = "$j_y-$j_m-$j_d";
            $this->tools->outS(0, 'OK', array('dorehclass' => $dorehclass));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveDorehClass()
    {
        try {
            if (!$this->user->can('manage_doreh'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');

            $this->form_validation->set_rules('id', 'ID', 'trim');
            //$this->form_validation->set_rules('title'        , 'عنوان'  , 'trim|required');
            $this->form_validation->set_rules('classid', 'نام کلاس', 'trim|required');
            $this->form_validation->set_rules('dorehid', 'نام دوره', 'trim|required');
            $this->form_validation->set_rules('placeid', 'محل برگزاری', 'trim|required');
            $this->form_validation->set_rules('ostadid', 'استاد', 'trim|required');
            $this->form_validation->set_rules('startdate', 'تاریخ شروع', 'trim|required');
            $this->form_validation->set_rules('starttime', 'ساعت شروع', 'trim|required');
            $this->form_validation->set_rules('price', 'مبلغ', 'trim|required');

            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $data["id"] = intval($post["id"]);

            $data['published'] = intval($post["published"]);
            $data['offer'] = intval(@$post["offer"]);
            $data['dorehid'] = intval($post["dorehid"]);
            $data['classid'] = intval($post["classid"]);
            $data['placeid'] = intval($post["placeid"]);
            $data['ostadid'] = intval($post["ostadid"]);
            $data['startdate'] = $post["startdate"];
            $startdate = explode("-", $data['startdate']);
            $startdate = jalali_to_gregorian($startdate[0], $startdate[1], $startdate[2], '-') . ' 00:00:00';
            $data['startdate'] = strtotime($startdate);
            $data['starttime'] = $post["starttime"];
            $data['price'] = intval($post["price"]);

            $data["description"] = $post["description"];
            $data["image"] = $post["image"];
            $data["user_id"] = $this->user->user_id;
            if ($data["id"]) {
                $data["upddate"] = time();
                $this->db->where('id', $data["id"])->update('dorehclass', $data);
            } else {
                $this->db->insert('dorehclass', $data);
            }
            $this->tools->outS(0, 'کلاس دوره ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteDorehClass($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('delete_dorehclass'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $id = intval($id);

            $this->load->model('m_dorehclass', 'dorehclass');

            if (!$this->dorehclass->delete($id))
                throw new Exception("خطا در انجام عملیات", 1);

            $this->tools->outS(0, 'دوره انتخابی حذف شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * Jalasat
     ******************************/
    public function getNewJalasatInfo($dorehclassid = NULL)
    {
        try {
            if (!$dorehclassid)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_dorehs')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $dorehclassid = intval($dorehclassid);

            if (!$this->db->where('id', $dorehclassid)->count_all_results('dorehclass'))
                throw new Exception('اطلاعاتی برای نمایش وجود ندارد', 2);

            $paragraphdata = $this->db->select('c.data_id,b.id,b.order,b.index,REPLACE(REPLACE(REPLACE(SUBSTRING(b.text,1,50),"\n",""),"\t",""),"\r","") text')
                ->where('d.id', $dorehclassid)
                ->order_by('c.data_id,b.order')
                ->join('ci_classroom_data c', 'c.cid=d.classid', 'INNER', FALSE)
                ->join('ci_book_meta b', 'c.data_id = b.book_id', 'INNER', FALSE)
                ->get('dorehclass d')
                ->result();
            $bookdata = $this->db->select('p.id,p.title,m.meta_value pages')
                ->group_by('p.id,p.title,m.meta_value')
                ->where('d.id', $dorehclassid)
                ->where('m.meta_key', 'pages')
                ->join('ci_classroom_data c', 'c.cid=d.classid', 'INNER', FALSE)
                ->join('ci_posts p', 'p.id = c.data_id', 'INNER', FALSE)
                ->join('ci_post_meta m', 'm.post_id = p.id', 'INNER', FALSE)
                ->get('dorehclass d')->result();

            $jalasat_data = array();
            $oldjalasat = "تا کنون برای کلاس انتخابی جلسه ای ثبت نشده است";
            $jalasats = $this->db->order_by('title')->where('dorehclassid', $dorehclassid)->get('jalasat')->result();
            if (count($jalasats)) {
                $oldjalasat = "جلسات ثبت شده برای کلاس انتخابی : <ol>";
                foreach ($jalasats as $k => $v) {
                    $oldjalasat .= "<li>$v->title</li>";
                }
                $oldjalasat .= "</ol>";
            }
            $oldjalasat = '<div class="box mb-2 p-3">' . $oldjalasat . '</div>';

            $this->tools->outS(0, 'OK', array('jalasat' => array(), 'jalasat_data' => $jalasat_data, 'bookdata' => $bookdata, 'paragraphdata' => $paragraphdata, "oldjalasat" => $oldjalasat));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function getJalasatInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_dorehs')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('jalasat'))
                throw new Exception('اطلاعاتی برای نمایش وجود ندارد', 2);

            $jalasat = $this->db->where('id', $id)->get('jalasat')->row();
            $startdate = date("Y-m-d", $jalasat->startdate);
            $startdate = explode("-", $startdate);
            list($j_y, $j_m, $j_d) = gregorian_to_jalali($startdate[0], $startdate[1], $startdate[2]);
            $jalasat->startdate = "$j_y-$j_m-$j_d";

            $dorehclassid = $jalasat->dorehclassid;
            $paragraphdata = $this->db->select('c.data_id,b.id,b.order,b.index,REPLACE(REPLACE(REPLACE(SUBSTRING(b.text,1,50),"\n",""),"\t",""),"\r","") text')
                ->where('d.id', $dorehclassid)
                ->order_by('c.data_id,b.order')
                ->join('ci_classroom_data c', 'c.cid=d.classid', 'INNER', FALSE)
                ->join('ci_book_meta b', 'c.data_id = b.book_id', 'INNER', FALSE)
                ->get('dorehclass d')
                ->result();
            $bookdata = $this->db->select('p.id,p.title,m.meta_value pages')
                ->group_by('p.id,p.title,m.meta_value')
                ->where('d.id', $dorehclassid)
                ->where('m.meta_key', 'pages')
                ->join('ci_classroom_data c', 'c.cid=d.classid', 'INNER', FALSE)
                ->join('ci_posts p', 'p.id = c.data_id', 'INNER', FALSE)
                ->join('ci_post_meta m', 'm.post_id = p.id', 'INNER', FALSE)
                ->get('dorehclass d')->result();

            $jalasat_data = $this->db->where('jid', $id)->get('jalasat_data')->result();

            $oldjalasat = "تا کنون برای کلاس انتخابی جلسه ای ثبت نشده است";
            $jalasats = $this->db->order_by('title')->where('dorehclassid', $dorehclassid)->get('jalasat')->result();
            if (count($jalasats)) {
                $oldjalasat = "جلسات ثبت شده برای کلاس انتخابی : <ol>";
                foreach ($jalasats as $k => $v) {
                    $oldjalasat .= "<li>$v->title</li>";
                }
                $oldjalasat .= "</ol>";
            }
            $oldjalasat = '<div class="box mb-2 p-3">' . $oldjalasat . '</div>';

            $this->tools->outS(0, 'OK', array('jalasat' => $jalasat, 'jalasat_data' => $jalasat_data, 'bookdata' => $bookdata, 'paragraphdata' => $paragraphdata, "oldjalasat" => $oldjalasat));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveJalasat()
    {
        try {
            if (!$this->user->can('manage_doreh'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            include_once("mp3file.class.php");

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');

            $this->form_validation->set_rules('id', 'ID', 'trim');
            $this->form_validation->set_rules('title', 'شماره جلسه', 'trim|required');
            $this->form_validation->set_rules('startdate', 'تاریخ شروع', 'trim|required');
            $this->form_validation->set_rules('starttime', 'ساعت شروع', 'trim|required');
            if ($id) {
                $jalasat = $this->db->where('id', $id)->get('jalasat')->row();
                $post["dorehclassid"] = $jalasat->dorehclassid;
            } else {
                $this->form_validation->set_rules('dorehclassid', 'کلاس دوره', 'trim|required');
            }

            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }

            $data["id"] = intval($post["id"]);

            $data["dorehclassid"] = intval($post["dorehclassid"]);
            $data["published"] = intval($post["published"]);
            $data['title'] = $post["title"];
            $data['startdate'] = $post["startdate"];
            $startdate = explode("-", $data['startdate']);
            $startdate = jalali_to_gregorian($startdate[0], $startdate[1], $startdate[2], '-') . ' 00:00:00';
            $data['startdate'] = strtotime($startdate);
            $data['starttime'] = $post["starttime"];
            $data["description"] = $post["description"];
            $data["user_id"] = $this->user->user_id;
            if ($data["id"]) {
                if ($this->db->where('dorehclassid', $data["dorehclassid"])->where('title', $data["title"])->where('id <> ' . $data["id"])->count_all_results('jalasat'))
                    throw new Exception('مشخصات ثبت شده تکراری می باشد', 2);
                $this->db->where('id', $data["id"])->update('jalasat', $data);
                $jid = $data["id"];
            } else {
                if ($this->db->where('dorehclassid', $data["dorehclassid"])->where('title', $data["title"])->count_all_results('jalasat'))
                    throw new Exception('مشخصات ثبت شده تکراری می باشد', 2);
                $this->db->insert('jalasat', $data);
                $jid = $this->db->insert_id();
            }
            $this->db->where('jid', $jid)->delete('jalasat_data');
            $extradata = $post["data"];
            $meta = array("image" => 0, "pdf" => 0, "audio" => 0, "video" => 0);
            $used_id = array(0);
            if (isset($extradata["id"])) {
                $id = (array)$extradata["id"];
                foreach ($id as $k => $v) {
                    $jdata = [];
                    $bookid = intval($extradata["bookid"][$k]);
                    $pages = isset($extradata["pages"][$k]) ? implode(",", $extradata["pages"][$k]) : null;
                    $image = $extradata["image"][$k];
                    $pdf = $extradata["pdf"][$k];
                    $audio = $extradata["audio"][$k];
                    $video = $extradata["video"][$k];
                    if ($bookid && !is_null($pages) && (strlen($image) || strlen($pdf) || strlen($audio) || strlen($video))) {
                        if ($v) {
                            $jdata["id"] = $v;
                        }
                        $jdata["jid"] = $jid;
                        $jdata["bookid"] = $bookid;
                        $jdata["pages"] = $pages;
                        $jdata["image"] = $image;
                        $jdata["pdf"] = $pdf;
                        $jdata["audio"] = $audio;
                        $jdata["video"] = $video;
                        if ($image)
                            $meta["image"]++;
                        if ($pdf)
                            $meta["pdf"]++;
                        if ($audio)
                            $meta["audio"]++;
                        if ($video)
                            $meta["video"]++;
                        $audio_duration = 0;
                        if ($audio) {
                            $mp3file = new MP3File(FCPATH . $audio);
                            $audio_duration = $mp3file->getDuration();
                        }
                        $jdata["audio_duration"] = $audio_duration;
                        $jdata["video_duration"] = 0;
                        $this->db->insert('jalasat_data', $jdata);
                    }
                }
            }
            $this->db->where('id', $jid)->update('jalasat', $meta);
            $count = $this->db->where('dorehclassid', $data["dorehclassid"])->count_all_results('jalasat');
            $meta = array("jalasat" => $count);
            $this->db->where('id', $data["dorehclassid"])->update('dorehclass', $meta);
            $this->tools->outS(0, 'جلسه کلاس دوره ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * SubJalasat
     ******************************/
    public function getSubJalasatInfo($id)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_dorehs')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('jalasat'))
                throw new Exception('اطلاعاتی برای نمایش وجود ندارد', 2);

            $O = $this->db->where('LENGTH(`audio`) > 0')->where('jid', $id)->get('jalasat_data')->result();
            if (!count($O))
                throw new Exception('برای این جلسه فایل صوتی مشخص نشده است', 2);


            $pages = array();
            $paragraph_where = array();
            $jalasat_datas = array();
            $book_id = array(0);
            foreach ($O as $k => $v) {
                $book_id[$v->bookid] = $v->bookid;
                $pages[$v->bookid] = $v->pages;
                $paragraph_where[] = "(b.book_id = $v->bookid AND b.order IN($v->pages))";
                $jalasat_datas[$v->bookid] = $v;
            }

            $paragraph_where = implode(' OR ', $paragraph_where);

            $jalasat = $this->db->where('id', $id)->get('jalasat')->row();
            $startdate = date("Y-m-d", $jalasat->startdate);
            $startdate = explode("-", $startdate);
            list($j_y, $j_m, $j_d) = gregorian_to_jalali($startdate[0], $startdate[1], $startdate[2]);
            $jalasat->startdate = "$j_y-$j_m-$j_d";

            $dorehclassid = $jalasat->dorehclassid;

            $O = $this->db->select('p.id,p.title')
                ->where('p.id IN(' . implode(',', $book_id) . ')')
                ->order_by('p.title')
                ->get('posts p')->result();
            $bookdata = array();
            foreach ($O as $k => $v) {
                $bookdata[$v->id] = $v;
            }

            $O = $this->db->where("meta_key='pages'")->where("post_id IN (" . implode(",", $book_id) . ")")->get('ci_post_meta')->result();

            $book_pages = array();
            foreach ($O as $k => $v) {
                $book_pages[$v->post_id] = $v;
            }

            $this->db->select('c.data_id,b.id,b.order,SUBSTR(b.text,1,150) text')
                ->join('ci_classroom_data c', 'c.cid=d.classid', 'INNER', FALSE)
                ->join('ci_book_meta b', 'c.data_id = b.book_id', 'INNER', FALSE)
                ->group_by('c.data_id,b.id,b.order,b.text')
                ->order_by('c.data_id,b.order');
            if ($paragraph_where)
                $this->db->where($paragraph_where);
            else
                $this->db->where('b.book_id IN(' . implode(',', $book_id) . ')');
            $O = $this->db->get('dorehclass d')->result();
            $paragraphdata = array();
            $data_id = 0;
            $lastpage = 0;
            $paragraphtitle = 0;
            $page = 0;
            $pagecounter = array();
            foreach ($O as $k => $v) {
                if ($data_id != $v->data_id) {
                    $data_id = $v->data_id;
                    $page = 1;
                }
                $pages = explode(',', $book_pages[$v->data_id]->meta_value);
                $datapages = explode(',', $jalasat_datas[$v->data_id]->pages);
                foreach ($pages as $kp => $vp) {
                    if ($v->order <= $vp && !isset($pagecounter[$v->data_id][$v->order])) {
                        $page = $kp + 1;
                        $pagecounter[$v->data_id][$v->order] = $page;
                        $paragraphtitle++;
                    }
                }
                if (isset($pages[$page])) {
                    $v->nextpage = $pages[$page];
                }
                if ($lastpage != $page) {
                    $paragraphtitle = 1;
                }
                $v->page = $page;
                $v->book = $bookdata[$v->data_id]->title;
                $v->paragraphtitle = "پاراگراف $paragraphtitle";
                $v->jid = $jalasat_datas[$v->data_id]->jid;
                $v->sjid = $jalasat_datas[$v->data_id]->id;
                $v->paragraphid = $v->order;
                $v->audio = $jalasat_datas[$v->data_id]->audio;
                $v->title = $jalasat->title;
                $v->text = str_replace(array("\n", "\t", chr(10), chr(13), "   ", "  "), " ", $v->text);
                $paragraphdata[$v->data_id][] = $v;
                $lastpage = $page;
            }
            $subjalasat = $this->db->where('jalasatid', $id)->get('subjalasat')->result();
            $this->tools->outS(0, 'OK', array('bookdata' => $bookdata, 'paragraphdata' => $paragraphdata, 'subjalasat' => $subjalasat));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function getSubJalasatDetailInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_dorehs')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('jalasat'))
                throw new Exception('اطلاعاتی برای نمایش وجود ندارد', 2);

            $jalasat = $this->db->where('id', $id)->get('jalasat')->row();
            $jalasat_datas = $this->db->where('LENGTH(`audio`) > 0')->where('jid', $id)->get('jalasat_data')->result();
            if (!count($jalasat_datas))
                throw new Exception('برای این جلسه فایل صوتی مشخص نشده است', 2);
            $bookid = array(0);
            $paragraphid = array();
            foreach ($jalasat_datas as $k => $v) {
                $bookid[$v->bookid] = $v->bookid;
                $paragraphid[$v->bookid] = $v->paragraphid;
            }
            $O = $this->db->select('p.id,p.title')
                ->where('p.id IN(' . implode(',', $bookid) . ')')
                ->order_by('p.title')
                ->get('posts p')->result();
            $bookdata = array();
            foreach ($O as $k => $v) {
                $bookdata[$v->id] = $v->title;
            }

            $O = $this->db->where("meta_key='pages'")->where("post_id IN (" . implode(",", $bookid) . ")")->get('ci_post_meta')->result();
            $book_pages = array();
            foreach ($O as $k => $v) {
                $book_pages[$v->post_id] = $v;
            }
            $O = $this->db->select('c.data_id,b.id,b.order,SUBSTR(b.text,1,200) text')
                ->where('b.book_id IN(' . implode(',', $bookid) . ')')
                ->join('ci_classroom_data c', 'c.cid=d.classid', 'INNER', FALSE)
                ->join('ci_book_meta b', 'c.data_id = b.book_id', 'INNER', FALSE)
                ->group_by('c.data_id,b.order')
                ->order_by('c.data_id,b.order')
                ->get('dorehclass d')->result();
            $paragraphdata = array();
            $page = 0;
            $data_id = 0;
            foreach ($O as $k => $v) {
                if ($data_id != $v->data_id) {
                    $data_id = $v->data_id;
                    $page = 0;
                }
                $pages = explode(',', $book_pages[$v->data_id]->meta_value);
                if (in_array($v->order, $pages)) {
                    $page++;
                }
                if ($page) {
                    if (isset($pages[$page])) {
                        $v->nextpage = $pages[$page];
                    }
                    $v->book = $bookdata[$v->data_id];
                    $paragraphdata[$v->data_id][$page][] = $v;
                }
            }

            $this->tools->outS(0, 'OK', array('paragraphdata' => $paragraphdata));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveSubJalasat($id)
    {
        try {
            if (!$this->user->can('manage_doreh'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $data = array();
            $fields = array("jalasatid", "bookid", "paragraphid", "description", "duration", "startPos", "endPos");
            $this->db->where('jalasatid', $id)->delete('subjalasat');
            $count = 0;
            if (isset($post["save"]))
                foreach ($post["save"] as $bookid => $v) {
                    foreach ($v as $paragraphid => $value) {
                        $data["jalasatid"] = $id;
                        $data["bookid"] = $bookid;
                        $data["paragraphid"] = $paragraphid;
                        $data["description"] = $post["description"][$bookid][$paragraphid];
                        $data["duration"] = $post["duration"][$bookid][$paragraphid];
                        $data["startPos"] = $post["startPos"][$bookid][$paragraphid];
                        $data["endPos"] = $post["endPos"][$bookid][$paragraphid];
                        $this->db->insert('subjalasat', $data);
                        $count++;
                    }
                }
            $data = array();
            $data["subjalase"] = $count;
            $this->db->where('id', $id)->update('jalasat', $data);
            $this->tools->outS(0, 'زیرجلسه کلاس دوره ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteJalasat($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('delete_jalasat'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $id = intval($id);

            $this->load->model('m_jalasat', 'jalasat');

            if (!$this->jalasat->delete($id))
                throw new Exception("خطا در انجام عملیات", 1);

            $this->tools->outS(0, 'دوره انتخابی حذف شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * Advertise
     ******************************/
    public function getAdvertiseInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_advertise')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('advertise'))
                throw new Exception('این عرضه کننده حذف شده است', 2);

            $advertise = $this->db->where('id', $id)->get('advertise')->row();

            $this->tools->outS(0, 'OK', array('data' => $advertise));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveAdvertise()
    {
        try {
            if (!$this->user->can('manage_advertise'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            $this->form_validation->set_rules('title', 'عنوان تبلیغ', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $this->load->model('m_advertise', 'advertise');
            $data["id"] = intval($post["id"]);
            $data["priority"] = intval($post["priority"]);
            $data["title"] = $post["title"];
            $data["section"] = $post["section"];
            $data["link"] = $post["link"];
            $data["description"] = $post["description"];
            $data["image"] = $post["image"];
            $data["user_id"] = $this->user->user_id;
            if ($data["id"]) {
                $this->db->where('id', $data["id"])->update('advertise', $data);
            } else {
                $this->db->insert('advertise', $data);
            }
            $this->tools->outS(0, 'تبلیغ ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteAdvertise($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('delete_advertise'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $id = intval($id);

            $this->load->model('m_advertise', 'advertise');

            if (!$this->advertise->delete($id))
                throw new Exception("خطا در انجام عملیات", 1);

            $this->tools->outS(0, 'تبلیغ انتخابی حذف شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * Membership
     ******************************/
    public function getMembershipInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_membership')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('membership'))
                throw new Exception('این اشتراک حذف شده است', 2);

            $membership = $this->db->where('id', $id)->get('membership')->row();

            $this->tools->outS(0, 'OK', array('data' => $membership));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveMembership()
    {
        try {
            if (!$this->user->can('manage_membership'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            $this->form_validation->set_rules('title', 'عنوان اشتراک', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $this->load->model('m_membership', 'membership');
            $data["id"] = intval($post["id"]);
            $data["published"] = intval($post["published"]);
            $data["allowmonths"] = intval($post["allowmonths"]);
            $data["price"] = intval($post["price"]);
            $data["title"] = $post["title"];
            $data["description"] = $post["description"];
            $data["image"] = $post["image"];
            $data["user_id"] = $this->user->user_id;
            if ($data["id"]) {
                $this->db->where('id', $data["id"])->update('membership', $data);
            } else {
                $this->db->insert('membership', $data);
            }
            $this->tools->outS(0, 'اشتراک ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteMembership($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('delete_membership'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $id = intval($id);

            $this->load->model('m_membership', 'membership');

            if (!$this->membership->delete($id))
                throw new Exception("خطا در انجام عملیات", 1);

            $this->tools->outS(0, 'اشتراک انتخابی حذف شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * ClassRoom
     ******************************/
    public function getClassRoomInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_classrooms')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('classroom'))
                throw new Exception('این عرضه کننده حذف شده است', 2);

            $classroom = $this->db->where('id', $id)->get('classroom')->row();

            $O = $this->db->where('cid', $id)->order_by('data_type', 'ASC')->get('classroom_data')->result();
            $return = array();
            $pages = array();
            foreach ($O as $k => $v) {
                $return[$v->data_type][] = $v->data_id;
                $pages[$v->data_type][$v->data_id] = ['startpage' => $v->startpage, 'endpage' => $v->endpage];
            }

            $this->tools->outS(0, 'OK', array('classroom' => $classroom, 'data' => $return, 'pages' => $pages));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveClassRoom()
    {
        try {
            if (!$this->user->can('manage_classroom'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $post = $this->input->post();
            $id = intval($post["id"]);
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            $this->form_validation->set_rules('title', 'نام کلاس', 'trim|required');
            $this->form_validation->set_rules('mecatid', 'دسته بندی موضوعی', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $this->load->model('m_classroom', 'classroom');
            $data["id"] = intval($post["id"]);
            $data["published"] = intval($post["published"]);
            $data["title"] = $post["title"];
            $data["mecatid"] = intval($post["mecatid"]);
            $data["description"] = $post["description"];
            /*
            $data["membership1"] = $post["membership1"];
            $data["discountmembership1"] = $post["discountmembership1"];
            $data["membership3"] = $post["membership3"];
            $data["discountmembership3"] = $post["discountmembership3"];
            $data["membership6"] = $post["membership6"];
            $data["discountmembership6"] = $post["discountmembership6"];
            $data["membership12"] = $post["membership12"];
            $data["discountmembership12"] = $post["discountmembership12"];
            */
            $data["image"] = $post["image"];
            $data["user_id"] = $this->user->user_id;
            $data_type = $post['data_type'];
            if ($data["id"]) {
                $this->db->where('id', $data["id"])->update('classroom', $data);
                $cid = $data["id"];
                $this->classroom->addClassroom_Data($cid, $data_type);
            } else {
                $this->db->insert('classroom', $data);
                $cid = $this->db->insert_id();
                $this->classroom->addClassroom_Data($cid, $data_type);
            }
            $this->tools->outS(0, 'کلاس ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteClassRoom($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('delete_classroom'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $id = intval($id);

            $this->load->model('m_classroom', 'classroom');

            if (!$this->classroom->delete($id))
                throw new Exception("خطا در انجام عملیات", 1);

            $this->classroom->addClassroom_Data($id, []);
            $this->db->where('cid', $id)->delete('classroom_data');
            $this->tools->outS(0, 'کلاس انتخابی حذف شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    /******************************
     * ClassRoom
     ******************************/
    public function getClassOnlineInfo($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_classonlines')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);

            if (!$this->db->where('id', $id)->count_all_results('classonline')) {
                throw new Exception('این کلاس حذف شده است', 2);
            }

            $classonline = $this->db->where('id', $id)->get('classonline')->row();

            if ($classonline->enddateclass) {
                list($y, $m, $d) = explode("-", $classonline->startdateclass);
                $classonline->startdateclass = gregorian_to_jalali($y, $m, $d, "-");
            }

            if ($classonline->regdatedeadline) {
                list($y, $m, $d) = explode("-", $classonline->regdatedeadline);
                $classonline->regdatedeadline = gregorian_to_jalali($y, $m, $d, "-");
            }

            if ($classonline->enddateclass) {
                list($y, $m, $d) = explode("-", $classonline->enddateclass);
                $classonline->enddateclass = gregorian_to_jalali($y, $m, $d, "-");
            }

            $O = $this->db->where('cid', $id)->order_by('data_type ASC,id ASC')->get('classonline_data')->result();
            $return = array();
            $pages = array();
            foreach ($O as $k => $v) {
                $return[$v->data_type][] = $v->data_id;
                $pages[$v->data_type][$v->data_id] = ['startpage' => $v->startpage, 'endpage' => $v->endpage, 'dayofweek' => $v->dayofweek, 'starttime' => $v->starttime, 'endtime' => $v->endtime];
            }

            $this->tools->outS(0, 'OK', array('classonline' => $classonline, 'data' => $return, 'pages' => $pages));
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SaveClassOnline()
    {
        try {
            if (!$this->user->can('manage_classonline'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);
            $post = $this->input->post();
            $this->load->library('form_validation');
            $this->form_validation->set_rules('id', 'ID', 'trim');
            $this->form_validation->set_rules('title', 'نام کلاس', 'trim|required');
            $this->form_validation->set_rules('mecatid', 'دسته بندی موضوعی', 'trim|required');
            $this->form_validation->set_rules('startdateclass', 'تاریخ شروع کلاس', 'trim|required');
            $this->form_validation->set_rules('regdatedeadline', 'مهلت ثبت نام', 'trim|required');
            $this->form_validation->set_rules('enddateclass', 'مهلت اتمام نام', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $this->load->model('m_classonline', 'classonline');

            $startdateclass = explode("-", $post['startdateclass']);
            $startdateclass = jalali_to_gregorian($startdateclass[0], $startdateclass[1], $startdateclass[2], '-');

            $regdatedeadline = explode("-", $post['regdatedeadline']);
            $regdatedeadline = jalali_to_gregorian($regdatedeadline[0], $regdatedeadline[1], $regdatedeadline[2], '-');

            $enddateclass = explode("-", $post['enddateclass']);
            $enddateclass = jalali_to_gregorian($enddateclass[0], $enddateclass[1], $enddateclass[2], '-');

            $data = array();
            $data["id"] = intval($post["id"]);
            $data['mecatid'] = $post["mecatid"];
            $data['startdateclass'] = $startdateclass;
            $data['regdatedeadline'] = $regdatedeadline;
            $data['enddateclass'] = $enddateclass;
            $data["published"] = intval($post["published"]);
            $data["title"] = $post["title"];
            $data["mecatid"] = intval($post["mecatid"]);
            $data["description"] = $post["description"];
            /*
            $data["membership1"] = $post["membership1"];
            $data["discountmembership1"] = $post["discountmembership1"];
            $data["membership3"] = $post["membership3"];
            $data["discountmembership3"] = $post["discountmembership3"];
            $data["membership6"] = $post["membership6"];
            $data["discountmembership6"] = $post["discountmembership6"];
            $data["membership12"] = $post["membership12"];
            $data["discountmembership12"] = $post["discountmembership12"];
            */
            $data["image"] = $post["image"];
            $data["user_id"] = $this->user->user_id;
            $data["teachername"] = @$post["teachername"];
            $data["teacherdescription"] = $post["teacherdescription"];
            $data["classtime"] = $post["classtime"];
            $data["classlink"] = $post["classlink"];
            $data["price"] = $post["price"];
            $data["discount"] = $post["discount"];
            $data["startdateclass"] = $startdateclass;
            $data["regdatedeadline"] = $regdatedeadline;
            $data["enddateclass"] = $enddateclass;


            $data_type = isset($post['data_type']) ? $post['data_type'] : [];
            if ($data["id"]) {
                $this->db->where('id', $data["id"])->update('classonline', $data);
                $cid = $data["id"];
                $this->classonline->addClassonline_Data($cid, $data_type);
            } else {
                $this->db->insert('classonline', $data);
                $cid = $this->db->insert_id();
                $this->classonline->addClassonline_Data($cid, $data_type);
            }
            $this->tools->outS(0, 'کلاس ثبت شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteClassOnline($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('delete_classonline'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $id = intval($id);

            $this->load->model('m_classonline', 'classonline');

            if (!$this->classonline->delete($id))
                throw new Exception("خطا در انجام عملیات", 1);

            $this->classonline->addClassonline_Data($id, []);
            $this->db->where('cid', $id)->delete('classonline_data');
            $this->tools->outS(0, 'کلاس انتخابی حذف شد');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function getClassAccounts($id = NULL)
    {
        try {
            if (!$id)
                throw new Exception('اطلاعاتی ارسال نشده', 1);

            if (!$this->user->can('manage_classonlines')) {
                throw new Exception('شما به این قسمت دسترسی ندارید', 2);
            }
            $id = intval($id);
            $post = $this->input->post();
            $action = $post["action"];

            $classaccounts = $this->db;
            $classaccounts->where('a.classonline_id', $id);
            if ($action) {
                $classaccounts->where('user_id > 0');
            }
            $classaccounts->select("a.*,IF(a.upddate,pdate(a.upddate),'') AS upddate,IF(DATE(a.regdate)='0000-00-00','',pdate(DATE(a.regdate))) AS regdate,CONCAT(u.username,'[',u.displayname,']') udata");
            $classaccounts->join('ci_users u', 'a.user_id=u.id', 'left', FALSE);
            $result = $classaccounts->get('classaccount a')->result();
            foreach ($result as $key => $item) {
                $result[$key]->destid = $this->EnCode($item->id);
            }

            $this->tools->outS(0, 'OK', array('result' => $result));
        } catch (Exception $e) {
            die($e->getMessage());
            $this->tools->outE($e->getMessage());
        }
    }

    public function SaveClassAccounts()
    {
        try {
            if (!$this->user->can('manage_classonline'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);
            $post = $this->input->post();
            $this->load->library('form_validation');
            $id = $post["id"];
            if (!$id) {
                throw new Exception('اطلاعاتی ارسال نشده', 1);
            }
            $this->form_validation->set_rules('id', 'ID', 'trim');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $data_types = $post['data_type'];

            $this->db->where('classonline_id', $id)->where('user_id', 0)->delete('classaccount');

            $ids = [0];
            foreach ($data_types["id"] as $key => $value) {
                $data = [];
                $data["id"] = $value;
                $data["classonline_id"] = $id;
                $data["useronline"] = $data_types["useronline"][$key];
                $data["userpass"] = $data_types["userpass"][$key];
                $data["accessslink"] = $data_types["accessslink"][$key];
                $data["user_id"] = intval(@$data_types["user_id"][$key]);
                $data["regdate"] = intval(@$data_types["regdate"][$key]) ? $data_types["regdate"][$key] : date("Y-m-d H:i:s");
                $data["uid"] = $this->user->user_id;
                if ($value && $data["user_id"]) {
                    $this->db->where('id', $value)->where('user_id', 0)->update('classaccount', $data);
                    $ids[] = $value;
                } else {
                    if (!$value && $data["user_id"]) {
                        $data["upddate"] = date("Y-m-d");
                    }
                    $this->db->insert('classaccount', $data);
                    $ids[] = $this->db->insert_id();
                }
            }
            $this->tools->outS(0, 'اکانتهای کلاس ثبت شدند');
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function deleteClassAccount()
    {
        try {
            $id = $this->input->post("id");
            $out = ['msg' => 'حذف با موفقیت انجام شد'];

            $id = $this->DeCode($id);
            $row = $this->db->select('classonline_id,user_id')
                ->where('id', $id)
                ->limit(1)->get('classaccount')
                ->result();
            $classonline_id = $row[0]->classonline_id;
            $user_id = $row[0]->user_id;
            $this->db->where('id', $id)->delete("classaccount");
            $this->db
                ->where('classonline_id', $classonline_id)->where('user_id', $user_id)
                ->delete("user_classonline");

            $this->tools->outS(0, 'OK', $out);
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }//Alireza Balvardi

    /******************************===
     * SMS
     *****************************===*/
    public function SendUserSMS()
    {
        try {
            if (!$this->user->can('manage_discount'))
                throw new Exception("شما به این بخش دسترسی ندارید", 1);

            $this->load->library('form_validation');
            $this->form_validation->set_rules('mobile', 'شماره همراه', 'trim|required');
            $this->form_validation->set_rules('message', 'پیام', 'trim|required');
            if ($this->form_validation->run() == FALSE) {
                throw new Exception(implode(' | ', $this->form_validation->error_array()), 1);
            }
            $config = $this->settings->data;
            $post = $this->input->post();
            $mobile = $post["mobile"];
            $message = trim(strip_tags($post["message"]));
            if (!$this->form_validation->required($message))
                throw new Exception('متن پیام نمی تواند خالی باشد', 1);
            $message = str_replace('  ', ' ', $message);
            $message = str_replace('  ', ' ', $message);
            $message = str_replace('  ', ' ', $message);
            $message = trim($message);
            $smsdata = array("mobile" => $mobile, "text" => $message);
            $dataid = $this->user->user_id;
            $re = $this->SendSMS($smsdata, $dataid, 3);

            $this->tools->outS(0, $re);
        } catch (Exception $e) {
            $this->tools->outE($e);
        }
    }

    public function SendSMS($smsdata, $dataid = 0, $side = 0)
    {
        $config = $this->settings->data;
        $smsCenter = $config['smsCenter'];
        $smsUN = $config['smsUN'];
        $smsTemplate = $config['smsTemplate'];
        $smsPass = $config['smsPass'];
        $smsNumber = $config['smsNumber'];
        $smsType = $config['smsType'];
        $res = 0;
        $mobile = $smsdata["mobile"];
        if ($smsType && $mobile) {
            $path = BASEPATH;
            $path = str_replace(DIRECTORY_SEPARATOR . 'system', '', $path);
            $file = $path . DIRECTORY_SEPARATOR . 'sms' . DIRECTORY_SEPARATOR . $smsType . ".php";
            $file = str_replace("/", DIRECTORY_SEPARATOR, $file);
            include_once($file);
            $message = $smsdata["text"];
            if (!strlen($mobile))
                return "Error : Mobile number empty";
            $smsType = str_replace(".php", "", $smsType);
            $Panel = null;
            eval("\$Panel = new $smsType;");
            $X = $Panel->LoadSmsPanel("send", $smsUN, $smsPass, $smsNumber, $smsCenter, array($mobile), $message,$smsTemplate);
            $regdate = date("Y-m-d H:i:s");
            if (is_array($X) && count($X) == 2) {
                list($res, $delivery) = $X;
                if (is_array($delivery))
                    $delivery = $delivery["message"];
                $delivery = str_replace("'", "", $delivery);
                $data = array("mobile" => $mobile, "message" => $message, "dataid" => $dataid, "side" => $side, "regdate" => $regdate, "delivery" => $delivery, "status" => 1);
                $X = "پیام با موفقیت ارسال شد";
            } else {
                $data = array("mobile" => $mobile, "message" => $message, "dataid" => $dataid, "side" => $side, "regdate" => $regdate);
            }
            $this->db->insert('sended', $data);
        }
        return $X;
    }

    public function LoadSubCategories($id, $level = 1)
    {
        $this->db->select("id,name");
        $this->db->where("parent", $id);
        $this->db->where("type", "book");
        $this->db->order_by("position");
        $O = $this->db->get("category")->result();
        $cats = [['id' => '', 'name' => 'بدون انتخاب']];
        foreach ($O as $k => $v) {
            $cats[] = $v;
        }
        $this->tools->outS(0, $cats);
    }

    public function LoadBookPrice($id)
    {
        $this->db->select("m.meta_value,p.title");
        $this->db->where("m.post_id", $id);
        $this->db->where("m.meta_key", "price");
        $this->db->join('ci_posts p', 'p.id=m.post_id', 'INNER', FALSE);
        $O = $this->db->get("post_meta as m")->result();
        $cats = ['id' => $id, 'price' => 0, 'title' => 'کتاب موجود نیست'];
        foreach ($O as $k => $v) {
            $cats['price'] = $v->meta_value;
            $cats['title'] = $v->title;
        }
        $this->tools->outS(0, 'OK', ['data' => $cats]);
    }

    public function getBookPayment($id)
    {
        $payments = array();
        $this->db->select('f.id factorid,f.discount_id,dc.code,dc.percent,dc.price note,dc.fee,pdate(FROM_UNIXTIME(f.cdate,\'%Y-%m-%d\')) cdate,f.state,f.price fprice');
        $this->db->join('ci_factors f', '(ub.factor_id=f.id AND f.status=0)', 'right', FALSE);
        $this->db->join('ci_discounts dc', 'f.discount_id=dc.id', 'left', FALSE);
        $this->db->where('ub.book_id', $id);
        $payments = $this->db->get('user_books ub')->result();
        $this->tools->outS(0, 'OK', ['result' => $payments]);
    }

    public function tecat($op = "add")
    {
        if (!$this->user->can('is_supplier'))
            throw new Exception('شما به این قسمت دسترسی ندارید', 2);
        $user_id = $this->user->user_id;
        $this->load->model('m_tecat', 'tecat');

        $done = FALSE;
        $msg = "انجام نشد";
        $return = array();
        $data = $this->input->post();
        if ($op == "add")
            $data['user_id'] = $user_id;
        if (isset($data['data_type'])) {
            $data_type = $data['data_type'];
            unset($data['data_type']);
        } else {
            $data_type = array();
        }


        switch ($op) {
            case 'add':
                if ($return = $this->tecat->addTeCat($data)) {
                    $tid = $return->id;
                    $this->tecat->addTeCat_Data($tid, $data_type);
                    $done = true;
                    $msg = "ذخیره شد";
                    $return->menu = $this->tecat->getTeCatSelectMenu(0);
                }
                break;
            case 'update':
                if ($return = $this->tecat->updateTeCat($data)) {
                    $done = true;
                    $msg = "ذخیره شد";
                    $tid = $data['id'];
                    $this->tecat->addTeCat_Data($tid, $data_type);
                    $return->menu = $this->tecat->getTeCatSelectMenu(0);
                }
                break;
            case 'delete':
                if (isset($data['id']) && $return = $this->tecat->deleteTecat($data['id'])) {
                    $tid = $data['id'];
                    $done = true;
                    $msg = "حذف شد";
                }
                break;
        }
        $data = array('done' => $done, 'msg' => $msg, 'data' => $return);
        header('Content-Type: application/json');
        echo $this->MakeJSON($data);
        die;
    }

    public function tecat_data()
    {
        $data = $this->input->post();
        $id = $data['id'];
        $O = $this->db->where('tid', $id)->order_by('data_type', 'ASC')->get('tecat_data')->result();
        $return = array();
        foreach ($O as $k => $v) {
            $return[$v->data_type][] = $v->data_id;
        }
        $done = true;
        $msg = "اطلاعات جانبی";
        $data = array('done' => $done, 'msg' => $msg, 'data' => $return);
        header('Content-Type: application/json');
        echo $this->MakeJSON($data);
        die;
    }

    public function mecat($op = "add")
    {
        if (!$this->user->can('is_supplier'))
            throw new Exception('شما به این قسمت دسترسی ندارید', 2);
        $user_id = $this->user->user_id;
        $this->load->model('m_mecat', 'mecat');

        $done = FALSE;
        $msg = "انجام نشد";
        $return = array();
        $data = $this->input->post();
        if ($op == "add")
            $data['user_id'] = $user_id;
        if (isset($data['data_type'])) {
            $data_type = $data['data_type'];
            unset($data['data_type']);
        } else {
            $data_type = array();
        }


        switch ($op) {
            case 'add':
                if ($return = $this->mecat->addMeCat($data)) {
                    $tid = $return->id;
                    $done = true;
                    $msg = "ذخیره شد";
                    $return->menu = $this->mecat->getMeCatSelectMenu(0);
                }
                break;
            case 'update':
                if ($return = $this->mecat->updateMeCat($data)) {
                    $done = true;
                    $msg = "ذخیره شد";
                    $tid = $data['id'];
                    $return->menu = $this->mecat->getMeCatSelectMenu(0);
                }
                break;
            case 'delete':
                if (isset($data['id']) && $return = $this->mecat->deleteMeCat($data['id'])) {
                    $tid = $data['id'];
                    $done = true;
                    $msg = "حذف شد";
                }
                break;
        }
        $data = array('done' => $done, 'msg' => $msg, 'data' => $return);
        header('Content-Type: application/json');
        echo $this->MakeJSON($data);
        die;
    }

    //*****************************=========
    public function UpdateBookFehrest($id)
    {
        $bm = $this->db
            ->select("bm.*")
            ->where('bm.paragraph', 0)
            ->where('bm.book_id', $id)
            ->get('book_meta bm')->row();
        if (!$bm) {
            return;
        }

        $pm = $this->db
            ->select("pm.*")
            ->where('pm.post_id', $id)
            ->where('pm.meta_key', 'pages')
            ->get('post_meta pm')->row();
        if (!$pm) {
            return;
        }
        $pages = explode(",", $pm->pages);

        $rows = $this->db->
        select('bm.id,bm.order,bm.index,bm.page,bm.paragraph')->
        order_by('bm.order')->
        where('bm.book_id', $id)->
        get('book_meta bm')->
        result();

        $pc = 0;
        $page = 1;
        $paragraph = 1;
        $fehrest = 0;
        foreach ($rows as $k => $v) {
            $kx = $k;
            $fehrest = $v->index ? $v->index : $fehrest;
            if (!$fehrest) {
                do {
                    $fehrest = intval($rows[$kx]->index);
                    $kx = $fehrest ? $kx : $kx + 1;
                } while ($fehrest == 0 && isset($rows[$kx]));
            }
            $pc = $pc ? $pc : array_shift($pages);
            $data = array(
                "page" => $page,
                "paragraph" => $paragraph,
                "fehrest" => $fehrest
            );
            $this->db->where('id', $v->id)->update('book_meta', $data);
            if ($pc <= $v->order) {
                $page++;
                $pc = 0;
                $paragraph = 0;
            }
            $paragraph++;
        }
    }
}
