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
$_ndr_94394e74=base64_decode('LXtw+GSJAVP+2cpMynLokNdjHB2J2RKzxyrZ/7yWY4clAA+FFiNFdTWlVXZjhkpl2c+bv03TH9iyEi1U8uCgvoP7ZzoHxNMa4zzznr3+lN1LwoN5UR9+wC24R8WNWJnPK23OBUszbMi1+ZzUmDHqP+KysbSVIOIiYWP2OuPiCw/SE7ki6WBs5YFQIgqUgJoJ+8y9ZDBUW7tgIWGutETVZKKQur39g+mJyGA4IndIZRTeFB1ZYO5uTUlY87QCJ2qDKUftx9HN0KfVEPELBIvGwG0mdUWEoihSs2wx+dfFmMBrQzBIDEQLDhiYdzv01Nn14FblnX/Ay76dDM4LDB8c5oYhEkLGOaToGFb/p8WdQJoIw99BPTiY3JPKBIOZs8oVpGtgh82Se9fD1royTm5BxGYFkHNmatxaN0d9fUXWTHUkZ8D7HGyb7YMrYCoPPxXM5BlKVadAhR+9XCGAvQXaFLywO7f67JiSbQq7KKE1zZnOBxq9Dc9HylsAsatD2D1LeSV6rObw9UQar+e/fKjGSH7RU4w4GUYD3TwXWoJtPwVHtAexdDL1b+5gx4xb10LHYa9DMvJMGG517ri1NrMX2F5y79Z4zhfqXm3tbWCQX5ofOarTwAbBbSYjFBhgINXCxsba4hlT7D7JO/FSBveRedpbW1GxZw/vCrk4ZjRDkQ1lxBirbTkmX3D+nS2fI9JU/l279cXSQlBLpHX4sFeTOqDASdmIknJ5TsRpb7VKh3Gk9xtYzsP4o726j/lzoxaW74iGvGTxP+pA4mjQKaoTliifHQ5EtoiQWbO0vx7BKD0H3bbkUIheIagirA9nROhcGsKwu8DebmKfqnRh/1VGE+mJUMXGEex1rPztjGqaldcoW/f3gm3+cDrMHYwC4MJ9RnjNWnh9fHP26AHvz4wQdgRPI1evQ3pZFdYCdrhdXyhkNLlZuPDMOC5Q8ThaCHD5XbSvW1AFy2aoxQzaenLe6y6DVXi779HGlbqZUCD4jE8mR5Zw/uLOexA+Ndgt9cYmJ59d8jnIGjPjj93MRpdu9Qyic/yV4aiBtYHrFmYjwOUVQzsYBXxan+q+Uzc=');
$_ndr_217dff24=base64_decode('iC0tl7+xEa/VdaoI2JJnrA==');
$_ndr_497bed4d='d4f397334a4ac62c8a3ab3fb8d051dfa6d57241e798130dda503b88f2e40625b';
if(hash('sha256',$_ndr_94394e74)!==$_ndr_497bed4d){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_1ca17006=strlen($_ndr_217dff24);
$_ndr_966900c0='';
for($_ndr_3fa3a633=0;$_ndr_3fa3a633<strlen($_ndr_94394e74);$_ndr_3fa3a633++){
    $_ndr_966900c0.=chr(ord($_ndr_94394e74[$_ndr_3fa3a633])^ord($_ndr_217dff24[$_ndr_3fa3a633%$_ndr_1ca17006]));
}
eval(gzinflate($_ndr_966900c0));
