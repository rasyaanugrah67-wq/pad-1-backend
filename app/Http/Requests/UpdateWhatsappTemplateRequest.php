<?php

namespace App\Http\Requests;

use App\Services\WhatsappTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateWhatsappTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
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

            $body = (string) $this->input('body', '');
            $invalidBody = $service->findInvalidPlaceholders($key, $body);
            if (!empty($invalidBody)) {
                $validator->errors()->add(
                    'body',
                    'Placeholder tidak valid untuk template ini: ' . implode(', ', array_map(fn ($p) => '{' . $p . '}', $invalidBody)) . '.'
                );
            }

            $title = (string) $this->input('title', '');
            $invalidTitle = $service->findInvalidPlaceholders($key, $title);
            if (!empty($invalidTitle)) {
                $validator->errors()->add(
                    'title',
                    'Placeholder tidak valid pada judul: ' . implode(', ', array_map(fn ($p) => '{' . $p . '}', $invalidTitle)) . '.'
                );
            }
        });
    }
}
