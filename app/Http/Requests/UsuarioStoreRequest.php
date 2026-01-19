<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @property string $nome
 * @property string $email
 * @property string $senha
 * @property bool|null $ativo
 * @property array<int,int>|null $perfis
 */
class UsuarioStoreRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->email) ? strtolower(trim($this->email)) : $this->email,
            'nome'  => is_string($this->nome) ? trim($this->nome) : $this->nome,
        ]);
    }

    public function rules(): array
    {
        return [
            'nome'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'string', 'email', 'max:100', 'unique:acesso_usuarios,email'],
            'senha'  => ['required', 'string', 'min:8'],
            'ativo'  => ['sometimes', 'boolean'],
            'perfis'   => ['sometimes', 'array'],
            'perfis.*' => ['integer', 'exists:acesso_perfis,id'],
        ];
    }
}
