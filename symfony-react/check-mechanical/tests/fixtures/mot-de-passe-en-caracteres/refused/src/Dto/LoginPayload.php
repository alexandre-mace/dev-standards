<?php
final class LoginPayload
{
    public function __construct(
        #[Assert\Length(max: PasswordHasherInterface::MAX_PASSWORD_LENGTH)]
        public readonly string $secret = '',
    ) {
    }
}
