<?php

namespace App\Http\Controllers;

use App\Product;
use App\Supplier;
use App\TrxPurchase;
use App\TrxPurchaseDetail;
use Illuminate\Http\Request;

use DB;

class TrxPurchaseController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {

        $data['purchase']   = TrxPurchase::with('user')->get()->toArray();
        $data['product']    = Product::get()->toArray();
        $data['supplier']   = Supplier::get()->toArray();

        return view('purchase', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $post = $request->except('_token');
        $user = auth()->user();

        // dd($post);

        for ($i = 0; $i < count($post['product']); $i++) {
            $post['purchase'][$i]['product_id']     = $post['product'][$i];
            $post['purchase'][$i]['supplier_id']    = $post['supplier'][$i];
            $post['purchase'][$i]['quantity']       = $post['quantity'][$i];
        }

        unset($post['product']);
        unset($post['supplier']);
        unset($post['quantity']);

        $totalPrice = 0;
        foreach ($post['purchase'] as $key => $value) {
            $getProduct = Product::where('id', $value['product_id'])->first();
            $post['purchase'][$key]['sub_total_price'] = $getProduct->price_purchase * $value['quantity'];
            $totalPrice += $getProduct->price_purchase * $value['quantity'];
        }

        $getLast = TrxPurchase::orderBy('id', 'desc')->first();
        if (!$getLast) {
            $code = 1;
        } else {
            $code = $getLast->id;
        }

        DB::beginTransaction();

        try {
            $savePurchase = TrxPurchase::create([
                'user_id'       => $user->id,
                'trx_code'      => 'TRX-PR-' . str_pad($getLast->id + 1, 4, '0', STR_PAD_LEFT) . '-' . time(),
                'total_price'   => $totalPrice
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->withErrors($e->getMessage())->withInput();
        }

        foreach ($post['purchase'] as $key => $value) {
            $post['purchase'][$key]['trx_purchase_id'] = $savePurchase->id;
        }

        try {
            $saveDetailPurchase = TrxPurchaseDetail::insert($post['purchase']);
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()->withErrors($e->getMessage())->withInput();
        }

        DB::commit();

        return redirect('purchase')->with('success', ['Success create new transaction purchase']);
    }
}
