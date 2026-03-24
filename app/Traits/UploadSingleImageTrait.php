<?php
namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

trait UploadSingleImageTrait {

    public function processSingleImage($image, $folder)
    {
        if ($image) {
            $name = time() . '_' . $image->getClientOriginalName();
          	$fileName = str_replace(' ', '_', $name);
            $path = $image->storeAs($folder, $fileName, 'public');
            return $path;
        }
        return null;

    }

    public function uploadImage($image, $folder) {
        if($image){

            $file = $image;
            $extension = $file->getClientOriginalExtension();
            $filename = time().'.'.$extension;
            $file->move($folder, $filename);

            return $folder.$filename;
        }
        return null;
    }

    // public function ckProcessSingleImage($image, $folder='articlePic/', $disk='public', $width = 300, $height = 300)
    // {
    //     if(isset($image)){
    //         $filename = time() . '.' .'webp';
    //         Image::make($image->getRealPath())->encode('webp', 100)->resize($width, $height)->save(public_path($folder . $filename));
    //         return $filename;
    //     }
    //     return null;
    // }

}
