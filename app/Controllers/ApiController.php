<?php

namespace App\Controllers;

use App\Libraries\Auth;

class ApiController extends BaseController
{
    private function input(): array
    {
        $data = $this->request->getJSON(true) ?? [];
        if (is_string($data['name'] ?? null)) $data['name'] = trim($data['name']);
        return $data;
    }

    private function invalid(array $data, array $rules)
    {
        $validation = service('validation');
        $validation->reset();
        $validation->setRules($rules);
        return $validation->run($data) ? null : $this->response->setStatusCode(422)
            ->setJSON(['message' => 'Periksa kembali data Anda.', 'errors' => $validation->getErrors()]);
    }

    public function register()
    {
        $data = $this->input();
        if (is_string($data['email'] ?? null)) $data['email'] = strtolower(trim($data['email']));
        if ($error = $this->invalid($data, ['name' => 'required|max_length[100]', 'email' => 'required|valid_email|max_length[190]|is_unique[users.email]', 'password' => 'required|min_length[8]|max_length[72]'])) return $error;
        $user = ['name' => trim($data['name']), 'email' => $data['email'], 'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT), 'token_version' => 0, 'created_at' => date('Y-m-d H:i:s')];
        db_connect()->table('users')->insert($user);
        $user['id'] = db_connect()->insertID();
        return $this->response->setStatusCode(201)->setJSON(['user' => Auth::publicUser($user), 'token' => Auth::issue($user), 'expires_in' => 3600]);
    }

    public function login()
    {
        $data = $this->input();
        if ($error = $this->invalid($data, ['email' => 'required|valid_email', 'password' => 'required|max_length[72]'])) return $error;
        $user = db_connect()->table('users')->where('email', strtolower(trim($data['email'])))->get()->getRowArray();
        // Run a password check even for unknown addresses to reduce timing differences.
        $hash = $user['password_hash'] ?? '$2y$12$lE.FFsrdkOZNBd2p3/EXdOgeOAnY4ko1SZlX3IaBy0sR.0bpHBEwS';
        if (!password_verify($data['password'], $hash) || !$user) return $this->response->setStatusCode(401)->setJSON(['message' => 'Email atau password tidak sesuai.']);
        return $this->response->setJSON(['user' => Auth::publicUser($user), 'token' => Auth::issue($user), 'expires_in' => 3600]);
    }

    public function me()
    {
        return $this->response->setJSON(['user' => Auth::publicUser(Auth::user())]);
    }

    public function updateUser()
    {
        $user = Auth::user(); $data = $this->input();
        if (is_string($data['email'] ?? null)) $data['email'] = strtolower(trim($data['email']));
        if ($error = $this->invalid($data, ['name' => 'required|max_length[100]', 'email' => 'required|valid_email|max_length[190]'])) return $error;
        if (db_connect()->table('users')->where('email', $data['email'])->where('id !=', $user['id'])->countAllResults()) return $this->response->setStatusCode(422)->setJSON(['message' => 'Email sudah digunakan.']);
        $changes = ['name' => trim($data['name']), 'email' => $data['email']];
        if (!empty($data['password'])) {
            if ($error = $this->invalid($data, ['password' => 'string|min_length[8]|max_length[72]', 'current_password' => 'required|string|max_length[72]'])) return $error;
            if (!password_verify($data['current_password'], $user['password_hash'])) return $this->response->setStatusCode(403)->setJSON(['message' => 'Password saat ini salah.']);
            $changes['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $changes['token_version'] = (int) $user['token_version'] + 1;
        }
        db_connect()->table('users')->where('id', $user['id'])->update($changes);
        $user = array_merge($user, $changes);
        return $this->response->setJSON(['user' => Auth::publicUser($user), 'token' => Auth::issue($user)]);
    }

    public function deleteUser()
    {
        $user = Auth::user(); $data = $this->input();
        if ($error = $this->invalid($data, ['password' => 'required|string|max_length[72]'])) return $error;
        if (!password_verify($data['password'], $user['password_hash'])) return $this->response->setStatusCode(403)->setJSON(['message' => 'Password salah.']);
        $db = db_connect(); $db->transStart();
        $db->table('bookings')->where(['user_id' => $user['id'], 'status' => 'pending'])->update(['status' => 'cancelled', 'payment_token' => null]);
        $db->table('users')->where('id', $user['id'])->delete();
        $db->transComplete();
        return $this->response->setJSON(['message' => 'Akun berhasil dihapus.']);
    }

    public function logout()
    {
        $user = Auth::user();
        db_connect()->table('users')->where('id', $user['id'])->set('token_version', 'token_version + 1', false)->update();
        return $this->response->setJSON(['message' => 'Berhasil keluar.']);
    }

    public function forgotPassword()
    {
        $data = $this->input();
        if ($error = $this->invalid($data, ['email' => 'required|valid_email'])) return $error;
        $result = ['message' => 'Jika email terdaftar, tautan reset password akan dikirim.'];
        $db = db_connect(); $user = $db->table('users')->where('email', strtolower(trim($data['email'])))->get()->getRowArray();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $db->table('password_resets')->where('user_id', $user['id'])->delete();
            $db->table('password_resets')->insert(['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 1800)]);
            $url = base_url('reset-password?token=' . $token);
            if (env('app.demo', false) && ENVIRONMENT === 'development') {
                $result['demo_reset_url'] = $url;
            } else {
                $mail = service('email');
                $mail->setTo($user['email'])->setSubject('Reset password Skybook')->setMessage("Reset password (berlaku 30 menit):\n" . $url);
                if (!$mail->send()) log_message('error', 'Password reset SMTP delivery failed.');
            }
        }
        return $this->response->setJSON($result);
    }

    public function resetPassword()
    {
        $data = $this->input();
        if ($error = $this->invalid($data, ['token' => 'required|exact_length[64]|alpha_numeric', 'password' => 'required|min_length[8]|max_length[72]'])) return $error;
        $db = db_connect(); $db->transBegin();
        $reset = $db->query('SELECT * FROM password_resets WHERE token_hash = ? AND expires_at > ? FOR UPDATE', [hash('sha256', $data['token']), date('Y-m-d H:i:s')])->getRowArray();
        if (!$reset) { $db->transRollback(); return $this->response->setStatusCode(422)->setJSON(['message' => 'Tautan reset tidak valid atau sudah kedaluwarsa.']); }
        $db->table('users')->where('id', $reset['user_id'])->set('token_version', 'token_version + 1', false)->update(['password_hash' => password_hash($data['password'], PASSWORD_DEFAULT)]);
        $db->table('password_resets')->where('user_id', $reset['user_id'])->delete();
        $db->transCommit();
        return $this->response->setJSON(['message' => 'Password diperbarui. Silakan masuk kembali.']);
    }
}
