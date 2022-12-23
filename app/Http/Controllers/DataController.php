<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Country;
use App\Models\Population;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DataController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //Get All countries with thier cities and population Data
        $response = Http::get('https://countriesnow.space/api/v0.1/countries/population/cities');
        $response = $response->object()->data;
        $countries = Country::with('cities.populations')->get();
        // Check if we have countries in our DB
        if($countries->count() != 0 ){
            foreach($countries as $countryKey => $country){
                //Sync Our Country with response
                $countryFound = 0;
                foreach($response as $res){
                    if($res->country ==  $country->name ){
                        $countryFound = 1;
                    }
                }
                if($countryFound == 0){
                    foreach($country->cities as $city){
                        foreach($city->populations as $pop){
                            $pop->delete(); 
                        }
                        $city->delete();
                    }
                    $country->delete();
                }else{
                    foreach($response as $res){
                        $city = City::where('country_id',$country->id)->where('name',$res->city)->with('populations')->first();
                        if(isset($city->populations)){
                            foreach($city->populations as $pop){
                                foreach($res->populationCounts as $ress){
                                    if($ress->year == $pop->year){
                                        $pop->update([
                                            'value'=> $ress->value,
                                            'sex'=> $ress->sex,
                                            'reliabilty'=> $ress->reliabilty,
                                        ]);
                                    }
                                }
                            }
                        }
                    }
                }   
            }
        }else{
            // Here is the case that we dont have any data in database
            // Add all countries to table countries (unique)
            $insertedCountries = collect();
            foreach($response as $res){
                if($insertedCountries->count() == 0){
                    $country = Country::create([
                        'name' => $res->country
                     ]);
                     $city = City::create([
                        'name'=>$res->city,
                        'country_id' => $country->id
                     ]);
                     foreach($res->populationCounts as $population){
                        if(is_numeric($population->year)){
                            Population::create([
                                'year' => $population->year,
                                'value' => is_numeric($population->value) ? $population->value:'',
                                'sex' => $population->sex,
                                'reliabilty' => $population->reliabilty,
                                'city_id' => $city->id,
                            ]);
                        }
                     }
                     $insertedCountries->add($country);
                }else{
                    if(!$insertedCountries->contains('name', $res->country)){
                        $country = Country::create([
                            'name' => $res->country
                         ]);
                         $city = City::create([
                            'name'=>$res->city,
                            'country_id' => $country->id
                         ]);
                         foreach($res->populationCounts as $population){
                            if(is_numeric($population->year)){
                                Population::create([
                                    'year' => $population->year,
                                    'value' => is_numeric($population->value) ? $population->value:'',
                                    'sex' => $population->sex,
                                    'reliabilty' => $population->reliabilty,
                                    'city_id' => $city->id,
                                ]);
                            }
                         }
                         $insertedCountries->add($country);
                    }else{
                        $country = $insertedCountries->last();
                        $city = City::create([
                            'name'=>$res->city,
                            'country_id' => $country->id
                         ]);
                         foreach($res->populationCounts as $population){
                            if(is_numeric($population->year)){
                                Population::create([
                                    'year' => $population->year,
                                    'value' => is_numeric($population->value) ? $population->value:'',
                                    'sex' => $population->sex,
                                    'reliabilty' => $population->reliabilty,
                                    'city_id' => $city->id,
                                ]);
                            }
                         }
                    }
                }              
            }
            
        }
        
        return $countries;
        
    }

    

}
