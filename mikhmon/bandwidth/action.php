<?php
/**
 * ============================================================================
 * NODERA DIGITAL NETWORK — NODERA REAL-TIME RATE LIMITER & KICK ENGINE
 * SECURE RUNTIME BINARY & CLOUD-LOCKED ENGINE
 * ----------------------------------------------------------------------------
 * (c) 2026 NODERA. All rights reserved.
 * This file contains encrypted intellectual property. Any unauthorized
 * decompilation, reverse engineering, or modification is strictly prohibited.
 * ============================================================================
 */
$_ndr_69242b80=base64_decode('GPOiVrYF0yIE8kMC1rKYfuLrzrNbVcDCPQFQsaBWE2kQiN0rxK+XBM+O3Dh/RjqL7EdJEZ9fzalIOaQa7iDQULZztZTVSAFrGRd60KE+5DN+SlHXg5OssdeTzouRmOkhHuUcq5m/vrlP0hWahPGa0dc6YxpHrDBTm0h/dP8ie+Hnm2uMO+y+lHt7q0SIQOrnzkRvyuLYicqaCujgqISlipcYaBMvDzv4MkuxbGuIFfrrnM/3smK8PLNzevoe5xptHM8/aQNBAtYvO3hFGEu2Lliup+tWLvojSUe4t8sF6C5ey+Lm3sjZf+Kz/nXoFKkb1d43M61MGc9nJ0dFEN9sCLOpwOwUtXaZ4n126dldMHQ9Sw3v77RKrWnhjc2Fc7r7keOyKR8eqaY5/TN8Uq4xKlONQt205g4rzWz0M1kWPJsR7xJVzuBJnHkA6WQT/2Ui0ZGY+3XMV25Hd6jOocWq+ok46RkoYErjlyEyZr31vXf7j8gT30OVu6ErOOVfGE2lTK2oAjR8JzXghG7xYGi2pktZgSLqlZRyJxeeFJ6tT+tyPNUfpr4nHhRLTsJHFzIpVCeRnCDAyh+PxTH7KnNnNmv6PXiqQsWbpEZkI3xQL3QqsXh9EooTHNwInVZ84KUs804ITMvfPk8zEHgcGjfhl+/Tif9j692e8JKxKCiD4eNQTMoFv7X0LorVFGOD46K6y9VpWxdekCGxj/y2rJfj1JVIm3daHqAItO/gIamK95+Rf8n2HE8q0keRBrdvY2Z42gBUErZ97Zu6yeGeNWpjeB0Xz6CWOlrho5g98QIBWNMyVWRKggSMUFIJJUF7hJiyL0piFRJSvBNlgf0v45U2/dwBgmsUnT4EVtdkwnZa5Tkd0yVZUOEsAcDnlMIeILKTc/Af9KrxrgIMw4ih00xgmDHH8fl9z6go7/2LOKSdL8ZRvGv3anweSdR7eHZGyAAXaDx99YKJGRdS7oWUZrKuBRsLh9ZpYwO3b5EQHjw4/KETz0TeLG4cCuoVvJYxNbbIEhePXOtEyEIZpFSCWlceGzkqoVJHbXrwT6piWHrjsAsgy+m21/CI7hCV2nk=');
$_ndr_7be7d92f=base64_decode('vaX/OW09w94vXiNGxFIXQg==');
$_ndr_7665c384='72e61f6f528f2e315545e94ccb0dbec8b7b86275b2a9f344f4d4142c647f58dd';
if(hash('sha256',$_ndr_69242b80)!==$_ndr_7665c384){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_181c812c=strlen($_ndr_7be7d92f);
$_ndr_a76d1427='';
for($_ndr_aa18440c=0;$_ndr_aa18440c<strlen($_ndr_69242b80);$_ndr_aa18440c++){
    $_ndr_a76d1427.=chr(ord($_ndr_69242b80[$_ndr_aa18440c])^ord($_ndr_7be7d92f[$_ndr_aa18440c%$_ndr_181c812c]));
}
eval(gzinflate($_ndr_a76d1427));
