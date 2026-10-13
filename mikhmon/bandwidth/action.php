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
$_ndr_608b5209=base64_decode('vKnkmBLAMyixtxFZQyRk20axiH3/kCDIiEQC6jXA78y00pvlYGp3DnrLjmPq0MYuSB0P3zuaLaP9fPZBe7Ys9RIp81pxjeFhrFIoizSoGJbaEBcZJ1ZMu2LWnNAEDhWEur9aZT16XrP6l0fBEWdmdHNgJdTjadBZLg0tL2q0h0RDwS1Cnylens4++R8d1hZCah4pBEYdacAvT7q7PRJZLzNCLt2Lytvyhw7jN/4e6V9Pxok5FqdcNgY2KKGLcebIuJV5p6eE4tyafioejd1Ki/z04SXy6xop/ALq7F6TFIv6kaQoeg05dVf2rC59glW+cYRx/QmJ+cXSYhUehUmQrRfzhiKwcJaTVzgkskzLzNGZEUshS3Gqp9yk35YQ5UZeNbn057vbSayMuGEnxzjNj/fXBBMQI+4heCmmaMyAwD61tVSbaiWplsxFuz+GaZmHdcveNdEJt2TyMvqVNFNWXy1ir9eMparpImRgPShjQdJf1Y7de4Z1sRRuar7KjrEA6PfuzJC5xz9VwTyq9f5KA+8Dx+xOUHR4klLMTws7s07WZpPRAnvHFKEOHJnSgc6M8H3XUoQFKhU6gGOgv+Wbk8+ge7YOhyWREQM2eOnG09GO6z6ztk/zFmlNzw3pdlmJVxROgm8a3kWGVSpHj6EdMkuJzzHHLj2URdfjc70VHUb0FozLG3AUJD+QRjgWdV4fb48vlbObcCsEyq7tOQEfcTES3bn+20ACAaqyejwcCzo1JY84uIrK2PLUVOz69ZrdfloS3BK4DZEPjLPFoPyf3blNiW4y/7rrFt1vqpeXpHaWDyKEJsFsWudMdxruEmQXixAk27aXXBnQxK90dgPKWHhbxKWwWN4O45I2mePMGZy5iWOX9CTMC3WixpmLtk4216pZOg40Tgi5htr6RtqcPZWdtzfZCkgiWrjZYzEL02P15i05zrn+Q2E+Ki3TXvyyzGY7OyZM+R3nq9fP8yRSoL9RwRjNpuO92tRCRamuAAS3lQIQiKv8AF9Q7s2ko0pttk3Jkk+BKEis4QbZz8Hivp1w55zjqJr6+u8wA+91TK6Eka94czVo5KXQiCI=');
$_ndr_c6f8fe82=base64_decode('Gf+598n4I9SaG3EdUcTr5w==');
$_ndr_b1a8a033='0138798d62c15fd41e7140d8a299cb5eb62dbbc9d4d07d288b9ade56cea25240';
if(hash('sha256',$_ndr_608b5209)!==$_ndr_b1a8a033){die('NODERA Security: Integrity verification failed (0x8F).');}
$_ndr_5dfcd812=strlen($_ndr_c6f8fe82);
$_ndr_adfd1e3a='';
for($_ndr_0f6e4ac1=0;$_ndr_0f6e4ac1<strlen($_ndr_608b5209);$_ndr_0f6e4ac1++){
    $_ndr_adfd1e3a.=chr(ord($_ndr_608b5209[$_ndr_0f6e4ac1])^ord($_ndr_c6f8fe82[$_ndr_0f6e4ac1%$_ndr_5dfcd812]));
}
eval(gzinflate($_ndr_adfd1e3a));
