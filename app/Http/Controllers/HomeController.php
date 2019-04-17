<?php  namespace App\Http\Controllers;

use App\Models\Post;
use App\Library\Markdown;
use Mail;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\DB;
use Validator, Input, Redirect ; 
use App\User;
use App\Models\Hotel;

class HomeController extends Controller {

	public function __construct()
	{
		parent::__construct();

		$this->data['pageLang'] = 'en';
		if(\Session::get('lang') != '')
		{
			$this->data['pageLang'] = \Session::get('lang');
		}	
	}

	/**
	 * Show the application dashboard to the user.
	 *
	 * @return Response
	 */
	public function index( Request $request) 
	{
        \App::setLocale(\Session::get('lang'));

		if(config('sximo.cnf_front') =='false' && $request->segment(1) =='' ) :
			return redirect('dashboard');
		endif;

		$page = $request->segment(1);

		\DB::table('tb_pages')->where('alias',$page)->update(array('views'=> \DB::raw('views+1')));

		if($page !='') {
			$sql = \DB::table('tb_pages')->where('alias','=',$page)->where('status','=','enable')->get();
			$row = $sql[0];
			if(file_exists(base_path().'/resources/views/layouts/'.config('sximo.cnf_theme').'/template/'.$row->filename.'.blade.php') && $row->filename !='')
			{
				$page_template = 'layouts.'.config('sximo.cnf_theme').'.template.'.$row->filename;
			} else {
				$page_template = 'layouts.'.config('sximo.cnf_theme').'.template.page';
			}

			if($row->access !='')
			{
				$access = json_decode($row->access,true);
			} else {
				$access = array();
			}

			// If guest not allowed 
			if($row->allow_guest !=1)
			{
				$group_id = \Session::get('gid');
				$isValid = (isset($access[$group_id]) && $access[$group_id] == 1 ? 1 : 0 );
				//print_r($access);die;

				if($isValid ==0)
				{
					return redirect('')
						->with(['message' => __('core.note_restric') ,'status'=>'error']);
				}
			}

			$this->data['pages'] = $page_template;
			$this->data['title'] = $row->title ;
			$this->data['subtitle'] = $row->sinopsis ;
			$this->data['pageID'] = $row->pageID ;
			$this->data['content'] = \PostHelpers::formatContent($row->note);
			$this->data['note'] = $row->note;
			if($row->template =='frontend') {
				$page = 'layouts.'.config('sximo.cnf_theme').'.index';
			}
			else {
				return view($page_template, $this->data);
			}
			return view( $page, $this->data);
		}
		else {
			$sql = \DB::table('tb_pages')->where('default','1')->get();
			if(count($sql)>=1)
			{
				$row = $sql[0];
				$this->data['title'] = $row->title ;
				$this->data['subtitle'] = $row->sinopsis ;
				if(file_exists(base_path().'/resources/views/layouts/'.config('sximo.cnf_theme').'/template/'.$row->filename.'.blade.php') && $row->filename !='')
				{
					$page_template = 'layouts.'.config('sximo.cnf_theme').'.template.'.$row->filename;
				} else {
					$page_template = 'layouts.'.config('sximo.cnf_theme').'.template.page';
				}
				$this->data['pages'] = $page_template;
				$this->data['pageID'] = $row->pageID ;
				
				$this->data['content'] = \PostHelpers::formatContent($row->note);

				$this->data['note'] = $row->note;

				$page = 'layouts.'.config('sximo.cnf_theme').'.index';

				// return $this->data;
				// die();
				return view( $page, $this->data);

			} else {
				return 'Please Set Default Page';
			}
		}
	}
	
	public function  getLang( Request $request , $lang='en')
	{
		$request->session()->put('lang', $lang);
		return  Redirect::back();
	}

	public function  getSkin($skin='sximo')
	{
		\Session::put('themes', $skin);
		return  Redirect::back();
	}

	public  function  postContact( Request $request)
	{
		$this->beforeFilter('csrf', array('on'=>'post'));
		$rules = array(
				'name'		=>'required',
				'subject'	=>'required',
				'message'	=>'required|min:20',
				'sender'	=>'required|email'
		);
		$validator = Validator::make(Input::all(), $rules);	
		if ($validator->passes()) 
		{
			$data = array('name'=>$request->input('name'),'sender'=>$request->input('sender'),'subject'=>$request->input('subject'),'notes'=>$request->input('message')); 
			$message = view('emails.contact', $data);
			$data['to'] = $this->config['cnf_email'];
			if($this->config['cnf_mail'] =='swift')
			{ 
				Mail::send('user.emails.contact', $data, function ($message) use ($data) {
		    		$message->to($data['to'])->subject($data['subject']);
		    	});	
			}  else {

				$headers  	= 'MIME-Version: 1.0' . "\r\n";
				$headers 	.= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
				$headers 	.= 'From: '.$request->input('name').' <'.$request->input('sender').'>' . "\r\n";
					mail($data['to'],$data['subject'], $message, $headers);		
			}

			return Redirect::to($request->input('redirect'))->with('message', \SiteHelpers::alert('success','Thank You , Your message has been sent !'));
				
		} else {
			return Redirect::to($request->input('redirect'))->with('message', \SiteHelpers::alert('error','The following errors occurred'))
			->withErrors($validator)->withInput();
		}		
	}	

	public function submit( Request $request )
	{
		$formID = $request->input('form_builder_id');

		$rows = \DB::table('tb_forms')->where('formID',$formID)->get();
		if(count($rows))
		{
			$row = $rows[0];
			$forms = json_decode($row->configuration,true);
			$content = array();
			$validation = array();
			foreach($forms as $key=>$val)
			{
				$content[$key] = (isset($_POST[$key]) ? $_POST[$key] : ''); 
				if($val['validation'] !='')
				{
					$validation[$key] = $val['validation'];
				}
			}
			
			$validator = Validator::make($request->all(), $validation);	
			if (!$validator->passes()) 
					return redirect()->back()->with(['status'=>'error','message'=>'Please fill required input !'])
							->withErrors($validator)->withInput();
			
			if($row->method =='email')
			{
				// Send To Email
				$data = array(
					'email'		=> $row->email ,
					'content'	=> $content ,
					'subject'	=> "[ " .config('sximo.cnf_appname')." ] New Submited Form ",
					'title'		=> $row->name
				);
			
				if( config('sximo.cnf_mail') =='swift' )
				{ 
					\Mail::send('sximo.form.email', $data, function ( $message ) use ( $data ) {
			    		$message->to($data['email'])->subject($data['subject']);
			    	});		

				}  else {

					$message 	 = view('sximo.form.email', $data);
					$headers  	 = 'MIME-Version: 1.0' . "\r\n";
					$headers 	.= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
					$headers 	.= 'From: '. config('sximo.cnf_appname'). ' <'.config('sximo.cnf_email').'>' . "\r\n";
						mail($data['email'], $data['subject'], $message, $headers);
				}
				
				return redirect()->back()->with(['status'=>'success','message'=> $row->success ]);

			} else {
				// Insert into database 
				\DB::table($row->tablename)->insert($content);
				return redirect()->back()->with(['status'=>'success','message'=>  $row->success  ]);
			}
		} else {
			return redirect()->back()->with(['status'=>'error','message'=>'Cant process the form !']);
		}
	}

	public function getLoad()
	{
		$result = \DB::table('tb_notification')->where('userid',\Session::get('uid'))->where('is_read','0')->orderBy('created','desc')->limit(5)->get();

		$data = array();
		$i = 0;
		foreach($result as $row)
		{
			if(++$i <=10 )
			{
				if($row->postedBy =='' or $row->postedBy == 0)
				{
					$image = '<img src="'.asset('uploads/images/system.png').'" border="0" width="30" class="img-circle" />';
				} else {
					$image = \SiteHelpers::avatar('30', $row->postedBy);
				}
				$data[] = array(
						'url'	=> $row->url,
						'title'	=> $row->title ,
						'icon'	=> $row->icon,
						'image'	=> $image,
						'text'	=> substr($row->note,0,100),
						'date'	=> date("d/m/y",strtotime($row->created))
					);
			}
		}

		$data = array(
			'total'	=> count($result) ,
			'note'	=> $data
			);
		 return response()->json($data);
	}

	public function posts( Request $request , $read = '') 
	{
		$posts = \DB::table('tb_pages')
					->select('tb_pages.*','tb_users.username',\DB::raw('COUNT(commentID) AS comments'))
					->leftJoin('tb_users','tb_users.id','tb_pages.userid')
					->leftJoin('tb_comments','tb_comments.pageID','tb_pages.pageID')
					->where('pagetype','post');
					if(!is_null($request->input('label'))) {
						$keyword = trim($request->input('label'));
						$posts = $posts->where('labels', 'LIKE' , "%{$keyword}%%" );
					}

					if($read !='') {
						$posts = $posts->where('alias',$read )->get(); 
					} else {
			$posts = $posts->groupBy('tb_pages.pageID')->paginate(12);
		}					

		$this->data['title']		= 'Post Articles';
		$this->data['posts']		= $posts;
		$this->data['pages']		= 'secure.posts.posts';
		base_path().'/resources/views/layouts/'.config('sximo.cnf_theme').'/blog/index.blade.php';

		if(file_exists(base_path().'/resources/views/layouts/'.config('sximo.cnf_theme').'/blog/index.blade.php'))
		{
			$this->data['pages'] = 'layouts.'.config('sximo.cnf_theme').'.blog.index';
		}	

		if($read !='') {
			if(file_exists(base_path().'/resources/views/layouts/'.config('sximo.cnf_theme').'/blog/view.blade.php'))
			{
				if(count($posts))
				{
					$this->data['posts'] = $posts[0];
					$this->data['comments']	= \DB::table('tb_comments')
												->select('tb_comments.*','username','avatar','email')
												->leftJoin('tb_users','tb_users.id','tb_comments.UserID')
												->where('PageID',$this->data['posts']->pageID)
												->get();
					\DB::table('tb_pages')->where('pageID',$this->data['posts']->pageID)->update(array('views'=> \DB::raw('views+1')));	
				} else {
					return redirect('posts');
				}
				$this->data['title']		= $this->data['posts']->title;
				$this->data['pages'] = 'layouts.'.config('sximo.cnf_theme').'.blog.view';
			}
		}
		$page = 'layouts.'.config('sximo.cnf_theme').'.index';
		return view( $page , $this->data);
	}

	public function comment( Request $request)
	{
		$rules = array(
			'comments'	=> 'required'
		);
		$validator = Validator::make($request->all(), $rules);	
		if ($validator->passes()) {

			$data = array(
					'userID'		=> \Session::get('uid'),
					'posted'		=> date('Y-m-d H:i:s') ,
					'comments'		=> $request->input('comments'),
					'pageID'		=> $request->input('pageID')
				);

			\DB::table('tb_comments')->insert($data);
			return redirect('posts/'.$request->input('alias'))
        		->with(['message'=>'Thank You , Your comment has been sent !','status'=>'success']);
		} else {
			return redirect('posts/'.$request->input('alias'))
				->with(['message'=>'The following errors occurred','status'=>'error']);	
		}
	}

	public function remove( Request $request, $pageID , $alias , $commentID )
	{
		if($commentID !='')
		{
			\DB::table('tb_comments')->where('commentID',$commentID)->delete();
			return redirect('posts/'.$alias)
				->with(['message'=>'Comment has been deleted !','status'=>'success']);
       
		} else {
			return redirect('posts/'.$alias)
				->with(['message'=>'Failed to remove comment !','status'=>'error']);
		}
	}

	public function set_theme( $id ) {
		session(['set_theme'=> $id ]);
		return response()->json(['status'=>'success']);
	}
	public function revenue() {
		$data_hotel= Hotel::groupBy('type')->get();
		$client=User::where('group_id', 4)->get();
        $corporate=User::find(session('uid'));
  
          $hcorporateData= Hotel::find($corporate->hotel_id);
         
		return view('invoices.revenue',compact('data_hotel','client','hcorporateData'));	
	}
	public function booking() {
		$data_hotel= DB::table('hotels')->groupBy('type')->get();
		$data_user= DB::table('tb_users')->where('group_id', 3)->get();
		return view('invoices.booking',compact('data_hotel','data_user'));	
	}
	public function client() {
		$data_hotel= DB::table('hotels')->groupBy('type')->get();

	   if(session('level')==4){
		$data_client= DB::table('tb_users')->where('id', session('uid'))->get();
	   }
	   else{
	   	$data_client= DB::table('tb_users')->where('group_id', 4)->get();
	   }

		return view('client.index',compact('data_hotel','data_client'));	
	}
	public function clientProfile($id) {
		$clientTrips = DB::table('user_trips')->where('entry_by', $id)->paginate(10);
		//$clientTrips= usertrips::where('entry_by', $id)->paginate(15);
        return view('client.clientProfile',compact('clientTrips'));		
	}
}
