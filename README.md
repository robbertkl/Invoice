# Invoice

Invoice generator.

## Usage

Docker-compose:

```yaml
invoice:
  image: robbertkl/invoice
  restart: always
  environment:
    INVOICE_COMPANY_NAME: My Company Name
    INVOICE_COMPANY_INFO: |
      My Company Address line 1
      My Company Address line 2

      +31 123 45 67 89
      email@mycompany.com
    INVOICE_COMPANY_KVK: 12345678
    INVOICE_COMPANY_VAT: NL123456789B01
    INVOICE_COMPANY_BANK: My Bank
    INVOICE_COMPANY_BIC: MYBANKBIC
    INVOICE_COMPANY_IBAN: NL00 BANK 0123456789
    INVOICE_DEFAULT_RECIPIENT: |
      Other Company Name
      Other Company Address line 1
      Other Company Address line 2
    INVOICE_ROWS: 6
    INVOICE_LEADING_ZEROS: 2
```

## License

Published under the [MIT License](http://www.opensource.org/licenses/mit-license.php).
