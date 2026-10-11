<?php

class LoginController {
    
    private $db; 

    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * POST /api/auth/login
     * 用户名/手机号 + 密码登录
     */
    public function login($request) {
        $phone = $request->input('phone');
        $password = $request->input('password');

        // 1. 验证输入
        if (empty($phone) || empty($password)) {
            return $this->responseError(400, '用户名和密码不能为空');
        }

        // 2. 查询用户
        $user = $this->db->queryOne("SELECT id, role, status, password FROM users WHERE phone = ?", [$phone]);

        if (!$user) {
            return $this->responseError(401, '用户名或密码错误');
        }

        // 3. 验证密码
        // 使用 password_verify 验证明文密码是否匹配存储的哈希值
        if (!password_verify($password, $user['password'])) {
            return $this->responseError(401, '用户名或密码错误');
        }

        // 4. 检查账号状态
        if ($user['status'] == 0) {
            return $this->responseError(403, '账号已被禁用，请联系管理员');
        }
        
        // 5. 登录成功：生成会话/Token，并返回
        $token = $this->generateAuthToken($user['id'], $user['role']); // 伪代码：生成 JWT 或 Session ID

        return $this->responseSuccess([
            'token' => $token, 
            'role' => $user['role'], 
            'user_id' => $user['id']
        ], '登录成功');
    }
    
    // ... responseSuccess/responseError/generateAuthToken 方法 ...
}