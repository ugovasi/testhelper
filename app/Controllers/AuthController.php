<?php

namespace App\Controllers;

use App\Models\RuoloModel;
use App\Models\UtenteModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('logged_in')) {
            return redirect()->to(site_url('admin/dashboard'));
        }

        return view('auth/login');
    }

    public function attemptLogin()
    {
        if (!$this->validate([
            'email'    => 'required|valid_email',
            'password' => 'required',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email    = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $utenteModel = new UtenteModel();
        $utente      = $utenteModel->findByEmail($email);

        // Messaggio generico identico per email inesistente/password errata/utente
        // disattivato: non rivelare quale delle tre condizioni si e' verificata.
        if (!$utente || !password_verify($password, $utente['password_hash']) || !$utente['attivo']) {
            return redirect()->back()->withInput()->with('error', 'Credenziali non valide.');
        }

        $ruoloModel = new RuoloModel();
        $permessi   = $utente['ruolo_id'] ? $ruoloModel->getPermessi($utente['ruolo_id']) : [];
        $ruolo      = $utente['ruolo_id'] ? $ruoloModel->find($utente['ruolo_id']) : null;

        session()->regenerate();
        session()->set([
            'logged_in'  => true,
            'user_id'    => $utente['id'],
            'nome'       => $utente['nome'],
            'email'      => $utente['email'],
            'ruolo_id'   => $utente['ruolo_id'],
            'ruolo_nome' => $ruolo['nome'] ?? null,
            'permessi'   => $permessi,
        ]);

        $utenteModel->update($utente['id'], ['ultimo_accesso' => date('Y-m-d H:i:s')]);

        $redirectUrl = session()->get('redirect_url');
        session()->remove('redirect_url');

        return redirect()->to($redirectUrl ?: site_url('admin/dashboard'));
    }

    public function logout()
    {
        session()->destroy();

        return redirect()->to(site_url('login'))->with('success', 'Logout effettuato.');
    }
}
