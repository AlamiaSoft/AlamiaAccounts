<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     * Note: company_code and allowed_companies are deliberately excluded
     * from mass assignment to prevent privilege escalation / cross-tenant reassignment.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'allowed_companies' => 'array',
        ];
    }

    /**
     * Determine if the user is authorized to access a given company domain.
     */
    public function canAccessCompany(?string $companyCode): bool
    {
        if ($companyCode === null || trim($companyCode) === '') {
            return false;
        }

        $companyCode = strtoupper(trim($companyCode));

        // Administrative roles have global access across all company domains
        $role = strtolower((string) ($this->role ?? ''));
        if (in_array($role, ['admin', 'owner', 'lead_accountant', 'super_admin'], true)) {
            return true;
        }

        // Check explicit allowed companies list
        $allowed = $this->allowed_companies;
        if (is_array($allowed)) {
            $normalized = array_map(fn($c) => strtoupper(trim((string)$c)), $allowed);
            if (in_array('*', $normalized, true) || in_array($companyCode, $normalized, true)) {
                return true;
            }
        }

        // Check primary / assigned company code
        if (!empty($this->company_code) && strtoupper(trim($this->company_code)) === $companyCode) {
            return true;
        }

        return false;
    }

    /**
     * Get all companies the user is authorized to access.
     */
    public function getAuthorizedCompanyCodes(): array
    {
        $role = strtolower((string) ($this->role ?? ''));
        if (in_array($role, ['admin', 'owner', 'lead_accountant', 'super_admin'], true)) {
            return ['*'];
        }

        $codes = [];
        if (is_array($this->allowed_companies)) {
            foreach ($this->allowed_companies as $code) {
                $c = strtoupper(trim((string)$code));
                if ($c !== '') {
                    $codes[] = $c;
                }
            }
        }

        if (!empty($this->company_code)) {
            $codes[] = strtoupper(trim($this->company_code));
        }

        return array_values(array_unique($codes));
    }
}
