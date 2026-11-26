<?php

namespace App\Http\Requests;

use App\Services\WhatsappTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewWhatsappTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'body' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $key = (string) $this->route('key');
            $service = app(WhatsappTemplateService::class);

            if (!$service->isValidKey($key)) {
                return;
            }

            $body = $this->input('body');
            if (is_string($body) && $body !== '') {
                $invalid = $service->findInvalidPlaceholders($key, $body);
                if (!empty($invalid)) {
                    $validator->errors()->add(
                        'body',
                        'Placeholder tidak valid untuk template ini: ' . implode(', ', array_map(fn ($p) => '{' . $p . '}', $invalid)) . '.'
                    );
                }
            }
        });
    }
}
