# POS credential rotation and configuration

The POS integration middleware accepts machine credentials only from the X-POS-Key
header or the Authorization: Bearer header. Do not send credentials in query strings
or request bodies.

## Configuration

Set ALAMIA_ACCOUNTS_POS_API_KEY_HASHES in deployment secret/environment configuration
to a JSON object whose keys are SHA-256 hashes of integration secrets and whose values
are authorized company codes:

    ALAMIA_ACCOUNTS_POS_API_KEY_HASHES={"<sha256-hash>":"KAMAL_EXPRESS"}

Generate a hash locally without printing or committing the secret:

    read -s -p "New POS secret: " POS_SECRET; echo
    printf %s "$POS_SECRET" | sha256sum
    unset POS_SECRET

Use the 64-character hexadecimal digest as the JSON key. Use a strong random secret
(at least 32 random bytes), store the raw value only in the POS client's secret store,
and deliver it through an approved secure channel. Do not commit the raw token or its
hash in source-controlled deployment files.

## Rotation checklist

1. Identify every deployed POS client and the company it is authorized to access.
2. Generate a new random secret for each integration and configure its SHA-256 hash
   against exactly one company code.
3. Deliver the raw secret securely to that client and update its request header.
4. Verify valid, invalid, missing, and cross-company requests before cutover.
5. Revoke old credentials by removing their hashes from configuration and reload
   configuration in every application instance.
6. Review available access logs for unexpected use and ensure authorization headers
   and X-POS-Key are not logged.
7. Never treat removing a token from the current source as a substitute for revoking
   every token that may already have been deployed. If a credential was committed,
   consider it compromised.

## Compatibility and rollout

This change intentionally removes embedded demo and live-looking credentials. POS
integrations will fail closed until a hash is configured. Coordinate secret deployment
and client updates before rolling out the new middleware. Existing clients that send
api_token in the request body must move to X-POS-Key or Bearer authentication.
