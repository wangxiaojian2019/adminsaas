<?php
namespace app\controller;

use support\Request;
use support\Log;

class UploadController
{
    public function upload(Request $request)
    {
        // 核心修复1：底层容错抓取，兼容前端 el-upload 默认的 'file' 字段，或自动嗅探首个可用文件流
        $file = $request->file('file');
        
        if (empty($file)) {
            $files = $request->file();
            if (!empty($files)) {
                $file = current($files);
            }
        }

        // 如果仍未抓取到文件，抛出精准排错信息，供前端追踪
        if (!$file || !$file->isValid()) {
            return json(['code' => 400, 'msg' => '阻断：未能从请求中解析到合法的物理文件流']);
        }

        // 提取指纹并赋予防冲突的独立哈希名
        $ext = $file->getUploadExtension() ?: 'png';
        $filename = uniqid('cert_') . '.' . $ext;
        
        // 核心修复2：目录沙箱的权限嗅探与自动构建
        $saveDir = public_path() . '/uploads';
        if (!is_dir($saveDir)) {
            @mkdir($saveDir, 0777, true);
        }
        
        $savePath = $saveDir . '/' . $filename;
        
        try {
            // 执行物理迁移写入
            $file->move($savePath);
            
            // 核心修复3：严格对齐前端强制要求的 JSON 结构校验锁 (code 必须为 200)
            return json([
                'code' => 200,
                'msg'  => 'success',
                'data' => [
                    'url' => '/uploads/' . $filename
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('文件写入底层报错：' . $e->getMessage());
            return json(['code' => 500, 'msg' => '服务器写入文件失败，请检查 public/uploads 目录读写权限']);
        }
    }
}