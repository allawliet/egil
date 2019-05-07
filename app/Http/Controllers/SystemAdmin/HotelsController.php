<?php
namespace App\Http\Controllers\SystemAdmin;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Rfp;
use App\User;
use App\Models\Invoices;
use App\Models\usertrips;
use App\Models\hotelamenities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
class HotelsController extends Controller {
    public function __construct() {
        if (Session::has('level')) {
            if (Session::get('level') != 1) return redirect()->back();
        } else return redirect()->back();
    }
    public function viewHotels(Request $request) {
        $q = (new Hotel)->newQuery();
        $searchField = "";
        if ($request->searchField) {
            $q->where('name', "LIKE", $request->searchField . "%");
            $searchField = $request->searchField;
        }
        $hotels = $q->get();
        $data_hotel = Hotel::groupBy('type')->get();
        $currentMonth = date('m');
        $purchases = Invoices::sum('invoices.amt_paid');
        $purchases_month = Invoices::whereRaw('MONTH(created_at)', [$currentMonth])->sum('invoices.amt_paid');
        /* pending amount*/
        $purchases_due_month = Invoices::whereRaw('MONTH(created_at)', [$currentMonth])->sum('invoices.est_amt_due');
        $purchases_due = Invoices::sum('invoices.est_amt_due');
        $trips = usertrips::all();
        $rfps_new = Rfp::where('status', 2)->get();
        $trip_booking = usertrips::whereRaw('MONTH(added) = ?', [$currentMonth])->get();
        return view('systemadmin.viewHotels', compact('hotels', 'searchField', 'purchases', 'purchases_due', 'trip_booking', 'data_hotel', 'rfps_new', 'purchases_month', 'purchases_due_month','trips'));
    }
    public function createHotels() {
        $amenities = hotelamenities::all();
        return view('systemadmin.createHotels', compact('amenities'));
    }
    public function storeHotels(Request $request) {
        $this->validate($request, ["hotel_code" => "required|max:191", "IATA_number" => "required", "service_type" => "required|max:191", "name" => "required|max:191", "zip" => "required", "address" => "required|max:191", "city" => "required|max:191", "type" => "required|max:191", "logo" => "required|max:555", "property" => "required|max:555", "rating" => "required|min:1|max:4", "active" => "required", ]);
        /*For hotel type logo*/
        $file = $request->file('logo')->getClientOriginalName();
        $destinationPath = './uploads/users/';
        $uploadSuccess = $request->file('logo')->move($destinationPath, $file);
        /*For hotel type property*/
        $filep = $request->file('property')->getClientOriginalName();
        $destinationPathp = './uploads/users/';
        $uploadSuccessp = $request->file('property')->move($destinationPathp, $filep);
        $hotel = new Hotel();
        $hotel->hotel_code = $request->hotel_code;
        $hotel->IATA_number = $request->IATA_number;
        $hotel->service_type = $request->service_type;
        $hotel->name = $request->name;
        $hotel->zip = $request->zip;
        $hotel->address = $request->address;
        $hotel->city = $request->city;
        $hotel->type = $request->type;
        $hotel->logo = $file;
        $hotel->property = $filep;
        $hotel->rating = $request->rating;
        $hotel->active = $request->active == "true" ? true : false;
        $hotel->save();
        $hotel->amenities()->sync($request->amenities);
        Session::flash("success", "New Hotel Added Successfully");
        return redirect()->action('SystemAdmin\HotelsController@viewHotels');
    }
    public function deleteHotels($id) {
        Hotel::find($id)->delete();
        Session::flash("success", "Record Deleted");
        return redirect()->back();
    }
    public function editHotels($id) {
        $hotel = Hotel::find($id);
        $amenities = hotelamenities::all();
        $hotel_type = Hotel::where('type', '!=', $hotel->type)->groupBy('type')->pluck('type');
        return view('systemadmin.editHotels', compact('hotel', 'amenities', 'hotel_type'));
    }
    public function hotelProfile($id) {
        $hotel = Hotel::find($id);
        $user = User::where('hotel_id', $id)->pluck('id');
        $data_hotel = Hotel::groupBy('type')->get();
        $amenities = hotelamenities::all();
        $currentMonth = date('m');
        $purchases = Invoices::where('invoices.hotel_name', $id)->whereRaw('MONTH(created_at) = ?', [$currentMonth])->sum('invoices.amt_paid');
        $purchases_due = Invoices::where('invoices.hotel_name', $id)->whereRaw('MONTH(created_at) = ?', [$currentMonth])->sum('invoices.est_amt_due');
        $hotel_contract = Rfp::get()->where("status", '!=', 3)->all();
        $hotel_trips = Rfp::where('user_id', $user)->orderBy('created_at', 'desc')->paginate(10);
        /*Total Booking of this month*/
        $trip_booking = usertrips::whereRaw('MONTH(added) = ?', [$currentMonth])->get();
        return view('systemadmin.hotelProfile ', compact('hotel', 'amenities', 'data_hotel', 'trip_booking', 'hotel_trips', 'purchases', 'purchases_due', 'hotel_contract'));
    }
    public function updateHotels(Request $request, $id) {
        $this->validate($request, ["hotel_code" => "required|max:191", "IATA_number" => "required", "service_type" => "required|max:191", "name" => "required|max:191", "zip" => "required", "address" => "required|max:191", "city" => "required|max:191", "type" => "required|max:191", "logo" => "required|max:555", "property" => "required|max:555", "rating" => "required|min:1|max:4", "active" => "required", ]);
        /*For hotel type logo*/
        $file = $request->file('logo')->getClientOriginalName();
        $destinationPath = './uploads/users/';
        $uploadSuccess = $request->file('logo')->move($destinationPath, $file);
        /*For hotel type property*/
        $filep = $request->file('property')->getClientOriginalName();
        $destinationPathp = './uploads/users/';
        $uploadSuccessp = $request->file('property')->move($destinationPathp, $filep);
        $hotel = Hotel::find($id);
        $hotel->hotel_code = $request->hotel_code;
        $hotel->IATA_number = $request->IATA_number;
        $hotel->service_type = $request->service_type;
        $hotel->name = $request->name;
        $hotel->zip = $request->zip;
        $hotel->address = $request->address;
        $hotel->city = $request->city;
        $hotel->type = $request->type;
        $hotel->logo = $file;
        $hotel->property = $filep;
        $hotel->rating = $request->rating;
        $hotel->active = $request->active == "true" ? true : false;
        $hotel->save();
        $hotel->amenities()->sync($request->amenities);
        Session::flash("success", "Hotel Updated Successfully");
        return redirect()->back();
    }
}
