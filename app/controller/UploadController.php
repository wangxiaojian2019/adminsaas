<?php
namespace app\controller;

use support\Request;
use support\Db;

class UploadController
{
    public function upload(Request $request)
    {
        $file = $request->file('file');
        $contractId = $request->post('contract_id');

        if ($file && $file->isValid()) {
            $ext = $file->getUploadExtension();
            $filename = uniqid() . '.' . $ext;
            
            // 确保目录存在，并赋予权限
            $uploadDir = public_path() . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $path = $uploadDir . $filename;
            $file->move($path);
            $url = '/uploads/' . $filename;

            // 业务参数捕获：若属于合同扫描件，直接触发落库
            if ($contractId) {
                Db::table('contracts')->where('id', $contractId)->update([
                    'paper_contract_url' => $url
                ]);
            }

            return json(['code' => 200, 'msg' => '文件已安全归档', 'url' => $url]);
        }
        
        return json(['code' => 400, 'msg' => '非法的文件传输请求']);
    }
}