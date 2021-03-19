<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Music;
use App\Models\Category;
use App\Models\Like;
use App\User;
use Validator;



class MusicController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $query = Music::query();
        $query1= Like::query();
        $query2=Category::query();
        $md = Music::get();
        $categories = Category::get();
        foreach ($categories as $category ){
            $c_musics = $category->musics;
            $music_counts = array();
            foreach($c_musics as $music){
                $music_counts[] = Like::selectRaw('count(music_id) as music_count,music_id')->where('music_id',$music->id)->groupBy('music_id')->orderBy('music_count','desc')->get();
            }
        }
        $count = count($music_counts)-1;
            $this->music_count_sort($music_counts, 0, $count);

            foreach($music_counts as $music_count){
                foreach($music_count as $music){
                    $musics[] = Music::where('id',$music->music_id)->get();
                }
            }

        return view('musics.index')->with([
            'categories' => $categories,
            'md' => $md,
            'category' => $category,
            'music_counts' => $music_counts,
            'musics' => $musics,
            ]);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $categories = Category::All();
        $artists = Music::select('artist')->distinct()->get();
        $user_id = Auth::id();
        if(!$user_id){
            return view("auth.login");
        }

        return view("musics.create")->with([
            'categories' => $categories,
            'artists' => $artists,
            'user_id' => $user_id
            ]);;
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {   
        $title = $request->input('title');
        $artist = $request->input('artist');
        $check = Music::where('artist',$artist)->where('title',$title)->exists();
        $this->validate($request,[
            'title' => ['required',
                        function($attribute, $value, $fail)use($check){
                            if($check){
                                return $fail('既に登録されている曲です');
                            }
                        }],
            'artist' => 'required|regex:/^[ア-ン゛゜ァ-ォャ-ョー]+$/u',
            'category_id' => 'required',
            'text' => 'nullable',
            'link' => 'nullable'
        ],[
            'title.required' => '曲名を入力してください',
            'artist.required' => 'アーティスト名を入力してください',
            'artist.regex' => 'アーティスト名は全角カタカナで入力してください',
            'category_id.required' => 'カテゴリを選択してください'
        ]);

        Music::create($request->all());
        $md= Music::get();
        // dd(count($md));
        $music_id = count($md);
        $user_id = Auth::id();

        return redirect()->route('groundlike', ['title' => $title]);
        // return redirect('/groundlike') -> with(
        //     'title' , $title
        // );
    }

    public function groundlike(Request $request)
  {
    $title =$request-> title;
    $query = Music::query();
    $musics= $query->where('title','like','%'.$title.'%' ) -> get();
    $musics= $query->where ('user_id', Auth::id()) -> get();
    foreach( $musics as $music){
        $music_id = $music -> id;
    }
        Like::create([
            'music_id' => $music_id,
            'user_id' =>  Auth::id(),
        ]);
    return redirect('/');
  }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        //キーワードを受け取る
        $Keyword = $request -> input('keyword');
        $Category_ID = $request -> input('category');
        #クエリ生成
        $query = Music::query();
        $query1= Category::query();
        //曲、カテゴリ両方検索されたら
        if (!empty($Category_ID) && !empty($Keyword) ){
            $musics= $query->where('title','like','%'.$Keyword.'%' ) -> get();
            $musics= $query->where ('category_id', $Category_ID) -> get();
            $Category = Category::find($Category_ID)-> name;
            if($i= count($musics)==0){
                $message = '存在しません';
                return view('musics.search')->with([
                    'message' => $message,
                ]);
            }else{
                $message = '存在しました';
                return view('musics.search')->with([
                    'message' => $message,
                    'musics' => $musics,
                    'Category' => $Category,
                    'Keyword' => $Keyword,
                ]);
            }
        }
        // 曲が入力されたら
        elseif(!empty($Keyword))
        {
            $musics= $query->where('title','like','%'.$Keyword.'%') -> get();
            $i=count($musics) ;
            if($i < 1 ){
            $message = "曲はありません";
            }else{
                $message = "カテゴリが".$i. "存在しました";
            }
            return view('musics.search')->with([
                'message' => $message,
                'musics' => $musics,
                'Keyword' => $Keyword,
            ]);
        // もしカテゴリが選択されたら
        }elseif (!empty($Category_ID)){
            $Category = Category::find($Category_ID)-> name;
            $musics= $query->where('category_id',$Category_ID)-> get();
            // foreach($categories as $category){
            // $id=$category-> id;
            // $musics= $query->where('category_id',$id)-> get();
            // }
            if (empty($musics)){
                $message = '曲がありません';
                return view('musics.search')->with([
                    'message' => $message,
                ]);
            }else{
                $count = $musics -> count();
                $message =  '曲が' . $count . '曲あります';
                return view('musics.search')->with([
                    'message' => $message,
                    'musics' => $musics,
                    'Category' => $Category,
                    'Category_ID' => $Category_ID,

                    // 'id' => $id,
                ]);
            }
        }else {
            $message = "検索結果ありません";
        }
    }

    public function like($id)
  {
    Like::create([
      'music_id' => $id,
      'user_id' => Auth::id(),
    ]);

    session()->flash('success', 'You Liked the Music.');

    return redirect()->back();
  }

    public function unlike($id)
    {
        $like = Like::where('music_id', $id)->where('user_id', Auth::id())->first();
        $like->delete();

        session()->flash('success', 'You Unliked the Music.');

        return redirect()->back();
  }

  function music_count_sort(&$list, $first, $last) {
    $firstPointer = $first;
    $lastPointer  = $last;
    //枢軸値を決める。配列の中央値
    $centerValue  = $list[intVal(($firstPointer + $lastPointer) / 2)][0]->music_count;

    //並び替えができなくなるまで
    do {
        //枢軸よりも左側で値が大きい場合はポインターは進める
        while ($list[$firstPointer][0]->music_count > $centerValue) {
            $firstPointer++;
        }
        //枢軸よりも右側で値が小さい場合はポインターを減らす
        while ($list[$lastPointer][0]->music_count < $centerValue) {
            $lastPointer--;
        }
        //この操作で左側と右側の値を交換する場所は特定

        if ($firstPointer <= $lastPointer) {
            //ポインターが逆転していない時は交換可能
            $tmp                 = $list[$lastPointer];
            $list[$lastPointer]  = $list[$firstPointer];
            $list[$firstPointer] = $tmp;
            //ポインタを進めて分割する位置を指定
            $firstPointer++;
            $lastPointer--;
        }
    } while ($firstPointer <= $lastPointer);

    if ($first < $lastPointer) {
        //左側が比較可能の時
        $this->music_count_sort($list, $first, $lastPointer);
    }

    if ($firstPointer < $last) {
        //右側が比較可能時
        $this->music_count_sort($list, $firstPointer, $last);
    }
}

}
