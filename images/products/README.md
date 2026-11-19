# Product photos

One photo per product. Each file name must match that product's `product_img`
value in the `products` table, for example `hp-probook-450.jpg`. Until a file
is here the shop shows a placeholder tile with the category name, so a missing
photo never shows as a broken image.

To see which names the shop expects:

```sql
SELECT product_title, product_img FROM products ORDER BY product_id;
```

## What works best

- Square, at least 1200 × 1200 pixels.
- The product centred on a plain white (#FFFFFF) or transparent background.
  The shop detects these and blends the background into the grey cards.
  Lifestyle photos also work; they are cropped to fill the card instead.
- JPG at about 80% quality, under 400 KB. PNG for transparent backgrounds.
- Lowercase file names with hyphens, no spaces.
