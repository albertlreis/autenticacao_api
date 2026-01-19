<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * @property string|null $nome
 * @property string|null $email
 * @property string|null $senha
 * @property bool|null $ativo
 * @property array<int,int>|null $perfis
 */
class UsuarioUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->email)) {
            $this->merge(['email' => strtolower(trim($this->email))]);
        }
        if ($this->has('nome') && is_string($this->nome)) {
            $this->merge(['nome' => trim($this->nome)]);
        }
    }

    public function rules(): array
    {
        $usuarioId = (int) ($this->route('usuario')?->id ?? $this->route('usuario'));

        return [
            'nome'   => ['sometimes', 'required', 'string', 'max:255'],
            'email'  => ['sometimes', 'required', 'string', 'email', 'max:100', Rule::unique('acesso_usuarios', 'email')->ignore($usuarioId)],
            'ativo'  => ['sometimes', 'boolean'],
            'perfis'   => ['sometimes', 'array'],
            'perfis.*' => ['integer', 'exists:acesso_perfis,id'],
            'senha'  => ['sometimes', 'nullable', 'string', 'min:8'],
        ];
    }
}
