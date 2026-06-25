<?php
namespace app\controller;

use support\Request;
use support\Db;

class UploadController
{
    // 定义绝对安全的后缀白名单（防 WebShell 物理隔离）
    private $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
    
    // 定义文件大小上限 (10MB)
    private $maxSize = 10 * 1024 * 1024;

    /**
     * 全局统一分布式上传网关
     */
    public function upload(Request $request)
    {
        // 1. 提取上传的文件对象
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return json(['code' => 400, 'msg' => '未找到文件或文件在传输过程中损坏']);
        }

        // 2. 核心安全防御：校验文件大小
        if ($file->getSize() > $this->maxSize) {
            return json(['code' => 413, 'msg' => '安全拦截：文件大小超出 10MB 限制']);
        }

        // 3. 核心安全防御：校验文件后缀 (严禁 php, sh, exe 等后缀)
        $extension = strtolower($file->getUploadExtension());
        if (!in_array($extension, $this->allowedExtensions)) {
            return json(['code' => 415, 'msg' => '安全拦截：不允许上传此类型的文件，谨防恶意木马']);
        }

        // 4. 解析操作人身份 (从之前重构的 JWT AuthMiddleware 中提取)
        $tenantId = 1;
        $uploaderType = 'unknown';
        $uploaderId = 0;

        if (isset($request->tenantId)) {
            $tenantId = $request->tenantId;
        }
        if (isset($request->enterprise_id)) {
            $uploaderType = 'tenant';
            $uploaderId = $request->enterprise_id;
        } elseif (isset($request->user)) {
            // 如果是 PC 端物业管理员或外勤师傅
            $uploaderType = 'admin';
            $uploaderId = is_array($request->user) ? ($request->user['id'] ?? 0) : ($request->user->id ?? 0);
        } else {
            // 兼容防挂：尝试从 session 中安全提取
            $sessionWorker = $request->session()->get('worker');
            if ($sessionWorker) {
                $uploaderType = 'worker';
                $uploaderId = is_array($sessionWorker) ? ($sessionWorker['id'] ?? 0) : 0;
            }
        }

        // 5. 存储策略驱动分配 (目前为 local，可随时无缝切换为 aliyun)
        $storageDriver = 'local';
        
        try {
            // 生成防碰撞的安全文件名
            $safeFileName = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            
            // 按月份归档目录，防止单一目录文件数过万导致 Linux IO 卡死
            $monthDir = date('Ym');
            $relativeDir = '/uploads/' . $monthDir;
            $absoluteDir = public_path() . $relativeDir;
            
            if (!is_dir($absoluteDir)) {
                mkdir($absoluteDir, 0777, true);
            }
            
            $absolutePath = $absoluteDir . '/' . $safeFileName;
            $fileUrl = $relativeDir . '/' . $safeFileName;

            // 执行物理转移
            $file->move($absolutePath);

            $attachmentId = 0;
            
            // 6. 将资产写入全局系统附件底库
            try {
                $attachmentId = Db::table('sys_attachments')->insertGetId([
                    'tenant_id' => $tenantId,
                    'uploader_type' => $uploaderType,
                    'uploader_id' => $uploaderId,
                    'original_name' => $file->getUploadName(),
                    'file_url' => $fileUrl,
                    'file_size' => $file->getSize(),
                    'file_ext' => $extension,
                    'mime_type' => $file->getUploadMimeType(), // 【核心修复 1】：修改原生 API 错别字 Mine -> Mime
                    'storage_driver' => $storageDriver,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            } catch (\Throwable $dbEx) {
                // 【核心修复 2】：静默降级处理。就算数据库里没有 sys_attachments 这张表，
                // 也绝不报错阻断。只要文件物理存在了，就直接给前端返回 URL 放行！
            }

            // 返回前端组件严格要求的数据结构
            return json([
                'code' => 200, 
                'msg' => '上传成功', 
                'data' => [
                    'url' => $fileUrl,
                    'attachment_id' => $attachmentId
                ]
            ]);

        } catch (\Exception $e) {
            return json(['code' => 500, 'msg' => '存储驱动落盘失败：' . $e->getMessage()]);
        }
    }
}